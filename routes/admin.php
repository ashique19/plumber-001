<?php

use App\Http\Controllers\Admin\AnalyticsExportController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\InvoicePdfController;
use App\Http\Controllers\Admin\InvoicePrintController;
use App\Http\Controllers\Admin\PrintTemplatePreviewController;
use App\Http\Controllers\Admin\QuotationPdfController;
use App\Http\Controllers\Admin\QuotationPrintController;
use App\Http\Controllers\Admin\Pos\ReceiptController;
use App\Http\Middleware\EnsureBranchActiveForPos;
use App\Http\Middleware\EnsureBusinessManageAccess;
use App\Http\Middleware\EnsurePosAccess;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Admin\StaffProfile;
use App\Livewire\Admin\StaffUsersHub;
use App\Livewire\Catalog\InventoryManager;
use App\Livewire\Catalog\MenuManager;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Expenses\ExpensesHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Livewire\Quotations\QuotationHub;
use App\Livewire\Operations\TeamHub;
use App\Livewire\Ops\BranchDashboard;
use App\Livewire\Payments\PaymentsDuesHub;
use App\Livewire\Pos\EndOfDay;
use App\Livewire\Pos\Home;
use App\Livewire\Pos\OrderHistory;
use App\Livewire\Pos\Terminal;
use App\Livewire\Purchases\CreatePurchase;
use App\Livewire\Purchases\SupplierHub;
use App\Livewire\Reports\OwnerAnalytics;
use App\Livewire\Stock\StockSearch;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:staff')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout');
    Route::get('/profile', StaffProfile::class)->name('admin.profile');

    Route::middleware('reports.access')->group(function (): void {
        Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');
        Route::get('/payments/dues', PaymentsDuesHub::class)->name('admin.payments.dues');
        Route::get('/expenses', ExpensesHub::class)->name('admin.expenses');
    });

    Route::middleware('owner.access')->group(function (): void {
        Route::get('/users', StaffUsersHub::class)->name('admin.users');
    });

    Route::middleware('team.access')->group(function (): void {
        Route::get('/team', TeamHub::class)->name('admin.team');
    });

    Route::middleware(EnsureBusinessManageAccess::class)->group(function (): void {
        Route::get('/business', BusinessSettings::class)->name('admin.business');
    });

    Route::middleware('inventory.access')->group(function (): void {
        Route::get('/catalog', MenuManager::class)->name('admin.catalog');
        Route::get('/inventory', InventoryManager::class)->name('admin.inventory');
        Route::get('/stock/search', StockSearch::class)->name('admin.stock.search');
        Route::get('/purchases/create', CreatePurchase::class)->name('admin.purchases.create');
        Route::get('/suppliers', SupplierHub::class)->name('admin.suppliers');
    });

    Route::middleware('reports.access')->group(function (): void {
        Route::get('/reports', OwnerAnalytics::class)->name('admin.reports');
        Route::get('/reports/export.csv', AnalyticsExportController::class)->name('admin.reports.export');
        Route::get('/customers', CustomerHub::class)->name('admin.customers');
    });

    Route::middleware('invoices.access')->group(function (): void {
        Route::get('/invoices', InvoiceHub::class)->name('admin.invoices');
        Route::get('/invoices/issue/{mode}', IssueInvoice::class)
            ->where('mode', 'standard|simplified')
            ->name('admin.invoices.issue');
        Route::get('/invoices/{invoice}/print', InvoicePrintController::class)->name('admin.invoices.print');
        Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('admin.invoices.pdf');
        Route::post('/invoices/{invoice}/issue-draft', [\App\Http\Controllers\Admin\InvoiceDraftLifecycleController::class, 'issue'])
            ->name('admin.invoices.drafts.issue');
        Route::post('/invoices/{invoice}/authorize-draft', [\App\Http\Controllers\Admin\InvoiceDraftLifecycleController::class, 'authorize'])
            ->name('admin.invoices.drafts.authorize');
        Route::delete('/invoices/{invoice}/draft', [\App\Http\Controllers\Admin\InvoiceDraftLifecycleController::class, 'destroy'])
            ->name('admin.invoices.drafts.destroy');
        Route::get('/quotations', QuotationHub::class)->name('admin.quotations');
        Route::get('/quotations/create', ManageQuotation::class)->name('admin.quotations.create');
        Route::get('/quotations/{quotation}/edit', ManageQuotation::class)->name('admin.quotations.edit');
        Route::get('/quotations/{quotation}/print', QuotationPrintController::class)->name('admin.quotations.print');
        Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)->name('admin.quotations.pdf');
        Route::get('/print/preview/{kind}/{template}', PrintTemplatePreviewController::class)
            ->whereIn('kind', ['invoice', 'quotation'])
            ->whereIn('template', ['modern', 'classic'])
            ->name('admin.print.template-preview');
    });

    Route::middleware(EnsurePosAccess::class)->prefix('pos')->name('admin.pos.')->group(function (): void {
        Route::get('/', Home::class)->name('home');
        Route::get('/branches/{branch}/terminal', Terminal::class)
            ->middleware(EnsureBranchActiveForPos::class)
            ->name('terminal');
        Route::get('/branches/{branch}/history', OrderHistory::class)->name('history');
        Route::get('/branches/{branch}/eod', EndOfDay::class)->name('eod');
        Route::get('/branches/{branch}/ops', BranchDashboard::class)->name('ops');
        Route::get('/orders/{order}/receipt', ReceiptController::class)->name('receipt');
    });
});
