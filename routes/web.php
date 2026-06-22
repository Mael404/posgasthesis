<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PrintedTransactionController;
use App\Http\Controllers\ShiftReconciliationController;
use App\Http\Controllers\StaffAnnouncementController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffTankDeliveryRecordController;
use App\Http\Controllers\StaffPumpController;
use App\Http\Controllers\StaffReadingController;
use App\Http\Controllers\StaffSalesController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::get('/signin', [AuthenticatedSessionController::class, 'create'])->name('signin');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::redirect('/signup', '/login')->name('signup');

Route::middleware('auth')->group(function () {
    // dashboard pages
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'dashboard'])
        ->middleware('role:vp_admin')
        ->name('dashboard.admin');

    Route::get('/vp-admin-db', [AdminDashboardController::class, 'dashboard'])
        ->middleware('role:vp_admin')
        ->name('admin.admin_db');

    Route::get('/semi-admin/dashboard', [DashboardController::class, 'semiAdmin'])
        ->middleware('role:semiadmin')
        ->name('dashboard.semi-admin');

    Route::get('/staff/dashboard', function () {
        return redirect()->route('staff.pump.index');
    })
        ->middleware('role:staff')
        ->name('dashboard.staff');

    Route::get('/staff-db', function () {
        return redirect()->route('staff.pump.index');
    })->name('staff.staff_db');

    Route::middleware('role:vp_admin')->group(function () {
        Route::get('/api/dashboard-data', [AdminDashboardController::class, 'getDashboardData'])
            ->name('dashboard.admin.data');

        Route::get('/admin/backup/database', [AdminDashboardController::class, 'backupDatabase'])
            ->name('admin.backup.db');

        Route::get('/admin/backup-database', [AdminDashboardController::class, 'backupDatabase']);

        Route::get('/admin/sales-monitoring', [AdminReportController::class, 'index'])
            ->name('admin.sales.index');

        Route::get('/admin/sales/print', [AdminReportController::class, 'print'])
            ->name('admin.sales.print');

        Route::prefix('admin/reconciliation')->name('admin.reconcile.')->group(function () {
            Route::get('/', [ShiftReconciliationController::class, 'index'])->name('index');
            Route::get('/details/{id}', [ShiftReconciliationController::class, 'getDetails'])->name('details');
            Route::get('/images/{reading}/{dispenser}/{type}', [ShiftReconciliationController::class, 'image'])
                ->where(['dispenser' => '[1-3]', 'type' => 'liters|sales'])
                ->name('image');
            Route::post('/approve', [ShiftReconciliationController::class, 'approve'])->name('approve');
            Route::post('/void-approval', [ShiftReconciliationController::class, 'voidApproval'])->name('voidApproval');
            Route::post('/reject', [ShiftReconciliationController::class, 'reject'])->name('reject');
            Route::post('/remarks', [ShiftReconciliationController::class, 'saveRemarks'])->name('remarks');
        });

        Route::prefix('admin/printed-transactions')->name('admin.printed-transactions.')->group(function () {
            Route::get('/', [PrintedTransactionController::class, 'index'])->name('index');
            Route::post('/bulk-mark', [PrintedTransactionController::class, 'bulkMark'])->name('bulk-mark');
            Route::delete('/bulk-delete', [PrintedTransactionController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/{batch}/print', [PrintedTransactionController::class, 'printBatch'])->name('print');
            Route::post('/{batch}/mark', [PrintedTransactionController::class, 'mark'])->name('mark');
            Route::delete('/{batch}', [PrintedTransactionController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('admin/announcements')->name('admin.announcements.')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->name('index');
            Route::post('/', [AnnouncementController::class, 'store'])->name('store');
            Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('/', function () {
                return redirect()->route('admin.sales.index');
            })->name('index');

            Route::get('/reading/{id}', function ($id) {
                return response()->json([
                    'message' => 'Reading details are not connected yet.',
                    'id' => $id,
                ], 501);
            })->name('reading.details');

            Route::post('/reading/approve', function () {
                return back()->with('status', 'Reading approval is not connected yet.');
            })->name('reading.approve');

            Route::post('/reading/reject', function () {
                return back()->with('status', 'Reading rejection is not connected yet.');
            })->name('reading.reject');
        });

        Route::get('/admin/shift-readings/notifications', function () {
            try {
                if (! Schema::hasTable('shift_readings') || ! Schema::hasTable('users')) {
                    return response()->json([
                        'notifications' => [],
                        'unread_count' => 0,
                    ]);
                }

                $notifications = DB::table('shift_readings')
                    ->join('users', 'shift_readings.user_id', '=', 'users.id')
                    ->select(
                        'shift_readings.id',
                        'shift_readings.period_from',
                        'shift_readings.period_to',
                        'shift_readings.status',
                        'shift_readings.created_at',
                        'users.name as staff_name'
                    )
                    ->where('shift_readings.status', 'Pending')
                    ->orderByDesc('shift_readings.created_at')
                    ->limit(10)
                    ->get()
                    ->map(function ($reading) {
                        $from = $reading->period_from
                            ? Carbon::parse($reading->period_from)->format('M d, Y')
                            : null;
                        $to = $reading->period_to
                            ? Carbon::parse($reading->period_to)->format('M d, Y')
                            : $from;

                        return [
                            'id' => (int) $reading->id,
                            'staffName' => $reading->staff_name ?: 'Staff',
                            'period' => $from && $from !== $to ? "{$from} - {$to}" : ($from ?: 'No period selected'),
                            'status' => 'Pending Verification',
                            'time' => $reading->created_at
                                ? Carbon::parse($reading->created_at)->diffForHumans()
                                : '',
                            'url' => route('admin.reconcile.index', [], false),
                        ];
                    })
                    ->values();

                return response()->json([
                    'notifications' => $notifications,
                    'unread_count' => $notifications->count(),
                ]);
            } catch (\Throwable) {
                return response()->json([
                    'notifications' => [],
                    'unread_count' => 0,
                ]);
            }
        })->name('admin.shift-readings.notifications');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stockIn');
        Route::post('/inventory/prices', [InventoryController::class, 'updatePrice'])->name('inventory.updatePrice');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('/customers/update', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/customers/ledger/{id}', [CustomerController::class, 'getLedger'])->name('customers.ledger');
        Route::get('/customers/print-ledger/{id}', [CustomerController::class, 'printLedger'])->name('customers.printLedger');
        Route::get('/customers/soa', [CustomerController::class, 'generateSOA'])->name('customers.soa');
        Route::get('/customers/{customerId}/unpaid-invoices', [CustomerController::class, 'getUnpaidInvoices'])->name('customers.unpaidInvoices');
        Route::post('/customers/payment', [CustomerController::class, 'addPayment'])->name('customers.pay');
        Route::post('/customers/payment/void', [CustomerController::class, 'voidPayment'])->name('customers.voidPayment');
        Route::post('/customers/status', [CustomerController::class, 'toggleStatus'])->name('customers.toggleStatus');

        Route::prefix('admin/billing')->name('admin.billing.')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::post('/generate', [BillingController::class, 'generate'])->name('generate');
            Route::post('/mark-paid', [BillingController::class, 'markPaid'])->name('markPaid');
            Route::post('/destroy', [BillingController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/print', [BillingController::class, 'printInvoice'])->name('print');
            Route::post('/{id}/print/signatories', [BillingController::class, 'savePrintInvoiceSignatories'])->name('print.signatories');
            Route::post('/{id}/rows', [BillingController::class, 'updateInvoiceRows'])->name('updateRows');
            Route::post('/{id}/rows/delete', [BillingController::class, 'deleteInvoiceRow'])->name('deleteRow');
            Route::get('/{id}/acknowledgement-receipt', [BillingController::class, 'printAcknowledgementReceipt'])->name('acknowledgementReceipt');
            Route::get('/{id}/acknowledgement-receipt/{paymentId}', [BillingController::class, 'printAcknowledgementReceipt'])->name('acknowledgementReceipt.payment');
            Route::post('/{id}/acknowledgement-receipt/signatories', [BillingController::class, 'saveAcknowledgementReceiptSignatories'])->name('acknowledgementReceipt.signatories');
            Route::post('/{id}/acknowledgement-receipt/{paymentId}/signatories', [BillingController::class, 'saveAcknowledgementReceiptSignatories'])->name('acknowledgementReceipt.signatories.payment');
            Route::post('/{id}/acknowledgement-receipt/issued', [BillingController::class, 'updateAcknowledgementReceiptIssued'])->name('acknowledgementReceipt.issued');
            Route::post('/{id}/acknowledgement-receipt/{paymentId}/issued', [BillingController::class, 'updateAcknowledgementReceiptIssued'])->name('acknowledgementReceipt.issued.payment');
        });

        Route::get('/staffs', [StaffController::class, 'index'])->name('staffs.index');
        Route::post('/staffs', [StaffController::class, 'store'])->name('staffs.store');
        Route::post('/staffs/status', [StaffController::class, 'toggleStatus'])->name('staffs.toggleStatus');
        Route::post('/staffs/password', [StaffController::class, 'resetPassword'])->name('staffs.resetPassword');
    });

    Route::prefix('staff')->name('staff.')->middleware('role:staff')->group(function () {
        Route::get('/pump', function () {
            return redirect()->route('staff.pump.index');
        })->name('pump.legacy');

        Route::get('/readings', function () {
            return redirect()->route('staff.readings.index');
        })->name('readings.legacy');

        Route::get('/sales/history', function () {
            return redirect()->route('staff.sales.history');
        })->name('sales.legacy');

        Route::get('/pump-station', [StaffPumpController::class, 'index'])->name('pump.index');

        Route::post('/pump-station/store', [StaffPumpController::class, 'storeSale'])->name('pump.store');

        Route::post('/pump-station/{id}/update', [StaffPumpController::class, 'updateSale'])
            ->whereNumber('id')
            ->name('pump.update');

        Route::get('/pump-station/customers/{customerId}/unpaid-invoices', [CustomerController::class, 'getUnpaidInvoices'])
            ->whereNumber('customerId')
            ->name('pump.unpaidInvoices');

        Route::match(['get', 'post'], '/pump-station/print-selected', [PrintedTransactionController::class, 'printSelected'])
            ->name('pump.printSelected');

        Route::post('/pump/void', [StaffPumpController::class, 'voidSale'])->name('pump.void');

        Route::post('/pump/payment', [StaffPumpController::class, 'storePayment'])->name('pump.storePayment');

        Route::post('/pump/payment/bypass', [StaffPumpController::class, 'bypassPayment'])->name('pump.bypassPayment');

        Route::post('/pump/payment/void-posted', [StaffPumpController::class, 'voidPostedPayment'])->name('pump.voidPostedPayment');

        Route::get('/shift-readings', [StaffReadingController::class, 'index'])->name('readings.index');

        Route::post('/shift-readings/store', [StaffReadingController::class, 'store'])->name('readings.store');

        Route::get('/readings/{reading}/images/{dispenser}/{type}', [StaffReadingController::class, 'showImage'])
            ->where(['dispenser' => '[1-3]', 'type' => 'liters|sales'])
            ->name('readings.image');

        Route::get('/readings/{id}/details', [StaffReadingController::class, 'getDetails'])->name('readings.details');

        Route::post('/shift-readings/{reading}/void', [StaffReadingController::class, 'voidReading'])->name('readings.void');

        Route::prefix('tank-delivery-records')->name('tank-delivery-records.')->group(function () {
            Route::get('/', [StaffTankDeliveryRecordController::class, 'index'])->name('index');
            Route::post('/', [StaffTankDeliveryRecordController::class, 'store'])->name('store');
            Route::put('/{record}', [StaffTankDeliveryRecordController::class, 'update'])
                ->whereNumber('record')
                ->name('update');
            Route::delete('/{record}', [StaffTankDeliveryRecordController::class, 'destroy'])
                ->whereNumber('record')
                ->name('destroy');
        });

        Route::get('/sales-history', [StaffSalesController::class, 'index'])->name('sales.history');

        Route::get('/sales-history/print', [StaffSalesController::class, 'print'])->name('sales.print');

        Route::prefix('printed-transactions')->name('printed-transactions.')->group(function () {
            Route::get('/', [PrintedTransactionController::class, 'index'])->name('index');
            Route::post('/', [PrintedTransactionController::class, 'storeSelected'])->name('store');
            Route::post('/bulk-mark', [PrintedTransactionController::class, 'bulkMark'])->name('bulk-mark');
            Route::get('/{batch}/print', [PrintedTransactionController::class, 'printBatch'])->name('print');
            Route::post('/{batch}/mark', [PrintedTransactionController::class, 'mark'])->name('mark');
        });

        Route::get('/billing', function () {
            return view('pages.staff.billing', ['title' => 'Billing']);
        })->name('billing.index');

        Route::get('/billing/print/{id}', [BillingController::class, 'printInvoice'])->name('billing.print');
        Route::post('/billing/print/{id}/signatories', [BillingController::class, 'savePrintInvoiceSignatories'])->name('billing.print.signatories');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [StaffAnnouncementController::class, 'index'])->name('index');
            Route::post('/mark-all-read', [StaffAnnouncementController::class, 'markAllRead'])->name('mark-all-read');
            Route::post('/{announcement}/read', [StaffAnnouncementController::class, 'markRead'])->name('read');
        });

        Route::prefix('announcements')->name('announcements.')->group(function () {
            Route::get('/', function () {
                return redirect()->route('staff.notifications.index');
            })->name('index');
            Route::post('/mark-all-read', [StaffAnnouncementController::class, 'markAllRead'])->name('mark-all-read');
            Route::post('/{announcement}/read', [StaffAnnouncementController::class, 'markRead'])->name('mark-read');
        });
    });

    // calender pages
    Route::get('/calendar', function () {
        return view('pages.calender', ['title' => 'Calendar']);
    })->name('calendar');

    // profile pages
    Route::get('/profile', function () {
        return view('pages.profile', ['title' => 'Profile']);
    })->name('profile');

    // form pages
    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Form Elements']);
    })->name('form-elements');

    // tables pages
    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
    })->name('basic-tables');

    // pages
    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Blank']);
    })->name('blank');

    // error pages
    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    // chart pages
    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Line Chart']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
    })->name('bar-chart');

    // ui elements pages
    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badges']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Images']);
    })->name('images');

    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Videos']);
    })->name('videos');
});
