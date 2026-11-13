<?php

namespace App\Services\Pos;

use App\Enums\InvoiceDocumentType;
use App\Enums\PosOrderStatus;
use App\Enums\PosPaymentMethod;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\StaffUser;
use App\Services\Loyalty\LoyaltyService;
use App\Exceptions\InsufficientIngredientStockException;
use App\Services\Inventory\IngredientStockService;
use App\Services\Operations\InventoryDeductionService;
use App\Services\PosInvoiceService;
use App\Support\Invoicing\StandardBuyerPayload;
use Illuminate\Support\Facades\DB;

class PosCheckoutService
{
    public function __construct(
        private readonly PosInvoiceService $posInvoiceService,
        private readonly LoyaltyService $loyaltyService,
        private readonly InventoryDeductionService $inventoryDeduction,
        private readonly IngredientStockService $ingredientStock,
    ) {}

    public function pay(PosOrder $order, PosPaymentMethod $method, StaffUser $cashier): PosOrder
    {
        if ($order->status !== PosOrderStatus::Open) {
            throw new \InvalidArgumentException('Only open orders can be paid.');
        }

        if ($order->line_items === []) {
            throw new \InvalidArgumentException('Cannot pay an empty order.');
        }

        if ($cashier->branch_id !== null && (int) $cashier->branch_id !== (int) $order->branch_id) {
            throw new \InvalidArgumentException('Cashier cannot pay orders for another branch.');
        }

        $tenant = BusinessSetting::singleton();
        if ($tenant->defaultInvoiceDocumentType() === InvoiceDocumentType::Standard) {
            $order->loadMissing('customer');
            StandardBuyerPayload::fromCustomer($order->customer);
        }

        return DB::transaction(function () use ($order, $method, $cashier, $tenant) {
            /** @var PosOrder $locked */
            $locked = PosOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== PosOrderStatus::Open) {
                throw new \InvalidArgumentException('Only open orders can be paid.');
            }

            $locked->loadMissing('branch');
            try {
                $this->ingredientStock->assertOrderFulfillable($locked, $locked->branch);
            } catch (InsufficientIngredientStockException $e) {
                throw $e;
            }

            $locked->forceFill([
                'status' => PosOrderStatus::Paid,
                'payment_method' => $method->value,
                'cashier_id' => $cashier->id,
                'paid_at' => now(),
            ])->save();

            $fresh = $locked->fresh();
            $this->ingredientStock->deductForPaidOrder($fresh, $cashier->id);
            $this->loyaltyService->earnForPaidOrder($fresh);
            $this->posInvoiceService->issueInvoiceForOrder($fresh);

            return $fresh->fresh();
        });
    }

    public function refund(PosOrder $order, StaffUser $cashier, string $reason): Invoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Refund reason is required.');
        }

        if ($cashier->branch_id !== null && (int) $cashier->branch_id !== (int) $order->branch_id) {
            throw new \InvalidArgumentException('Cashier cannot refund orders for another branch.');
        }

        return DB::transaction(function () use ($order, $reason, $cashier): Invoice {
            /** @var PosOrder $locked */
            $locked = PosOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->refund_credit_note_id !== null) {
                return $locked->refundCreditNote()->firstOrFail();
            }

            if ($locked->status === PosOrderStatus::Refunded) {
                throw new \InvalidArgumentException('Order is already refunded.');
            }

            if ($locked->status !== PosOrderStatus::Paid) {
                throw new \InvalidArgumentException('Only paid orders can be refunded.');
            }

            $credit = $this->posInvoiceService->issueRefundCreditNote($locked, $reason);
            $this->inventoryDeduction->restoreForRefundedOrder($locked->fresh(), $cashier->id);

            return $credit;
        });
    }
}
