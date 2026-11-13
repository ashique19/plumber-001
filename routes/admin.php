<?php

use App\Http\Controllers\Admin\AnalyticsExportController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\InvoicePrintController;
use App\Http\Controllers\Admin\Pos\ReceiptController;
use App\Http\Middleware\EnsureBranchActiveForPos;
use App\Http\Middleware\EnsureBusinessManageAccess;
use App\Http\Middleware\EnsureInventoryAccess;
use App\Http\Middleware\EnsureInvoicesAccess;
use App\Http\Middleware\EnsurePosAccess;
use App\Http\Middleware\EnsureReportsAccess;
use App\Http\Middleware\EnsureStaffManageAccess;
use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Catalog\InventoryManager;
use App\Livewire\Catalog\MenuManager;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Operations\TeamHub;
use App\Livewire\Ops\BranchDashboard;
use App\Livewire\Pos\EndOfDay;
use App\Livewire\Pos\Home;
use App\Livewire\Pos\OrderHistory;
use App\Livewire\Pos\Terminal;
use App\Livewire\Reports\OwnerAnalytics;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:staff')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout');

    Route::middleware('reports.access')->group(function (): void {
        Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');
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
