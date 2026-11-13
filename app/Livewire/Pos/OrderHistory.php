<?php

namespace App\Livewire\Pos;

use App\Enums\PosOrderStatus;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\Branch;
use App\Models\PosOrder;
use App\Models\BusinessSetting;
use App\Services\Pos\PosCheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.pos')]
class OrderHistory extends Component
{
    use DispatchesToasts;
    use WithPagination;

    public int $branchId;

    public BusinessSetting $business;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showRefundModal = false;

    public ?int $refundOrderId = null;

    public string $refundReason = '';

    public ?string $refundOrderNumber = null;

    public ?string $refundAmount = null;

    public function mount(Branch $branch): void
    {
        $user = Auth::guard('staff')->user();
        if ($user->branch_id !== null && (int) $user->branch_id !== (int) $branch->id) {
            abort(403);
        }

        $this->branchId = $branch->id;
        $this->business = BusinessSetting::singleton();
    }

    public function openRefundModal(int $orderId): void
    {
        $order = $this->resolveRefundableOrder($orderId);

        $this->refundOrderId = $order->id;
        $this->refundOrderNumber = $order->order_number;
        $this->refundAmount = number_format((float) $order->total_inc_vat, 2);
        $this->refundReason = '';
        $this->resetValidation();
        $this->showRefundModal = true;
    }

    public function cancelRefundModal(): void
    {
        $this->resetRefundModal();
    }

    public function confirmRefund(PosCheckoutService $checkout): void
    {
        if ($this->refundOrderId === null) {
            return;
        }

        $this->validate([
            'refundReason' => ['required', 'string', 'max:240'],
        ]);

        $order = $this->resolveRefundableOrder($this->refundOrderId);

        try {
            $checkout->refund($order, Auth::guard('staff')->user(), $this->refundReason);
        } catch (\InvalidArgumentException $e) {
            $this->addError('refundReason', $e->getMessage());
            $this->resetRefundModal();

            return;
        }

        $this->resetRefundModal();
        $this->toastSuccess(__('pos.refund_issued'));
    }

    public function render(): View
    {
        $orders = PosOrder::query()
            ->where('branch_id', $this->branchId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('order_number', 'like', $term)
                        ->orWhere('payment_method', 'like', $term);
                });
            })
            ->when(
                $this->statusFilter === 'all',
                fn ($q) => $q->whereIn('status', ['paid', 'refunded', 'cancelled']),
                fn ($q) => $q->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.pos.order-history', [
            'branch' => Branch::query()->findOrFail($this->branchId),
            'orders' => $orders,
        ]);
    }

    private function resolveRefundableOrder(int $orderId): PosOrder
    {
        $order = PosOrder::query()->findOrFail($orderId);
        if ((int) $order->branch_id !== $this->branchId) {
            abort(403);
        }

        if ($order->status !== PosOrderStatus::Paid) {
            throw new \InvalidArgumentException(__('pos.refund_not_allowed'));
        }

        if ($order->invoice_id === null) {
            throw new \InvalidArgumentException(__('pos.refund_no_invoice'));
        }

        return $order;
    }

    private function resetRefundModal(): void
    {
        $this->showRefundModal = false;
        $this->refundOrderId = null;
        $this->refundReason = '';
        $this->refundOrderNumber = null;
        $this->refundAmount = null;
    }
}
