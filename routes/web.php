<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PrintedTransactionController;
use App\Http\Controllers\ShiftReconciliationController;
use App\Http\Controllers\StaffController;
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

    Route::get('/staff/dashboard', [DashboardController::class, 'staff'])
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
            Route::post('/reject', [ShiftReconciliationController::class, 'reject'])->name('reject');
            Route::post('/remarks', [ShiftReconciliationController::class, 'saveRemarks'])->name('remarks');
        });

        Route::prefix('admin/printed-transactions')->name('admin.printed-transactions.')->group(function () {
            Route::get('/', [PrintedTransactionController::class, 'index'])->name('index');
            Route::get('/{batch}/print', [PrintedTransactionController::class, 'printBatch'])->name('print');
            Route::post('/{batch}/mark', [PrintedTransactionController::class, 'mark'])->name('mark');
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

    Route::middleware('role:staff')->group(function () {
        Route::get('/staff/pump', function () {
            return redirect()->route('staff.pump.index');
        });

        Route::get('/staff/readings', function () {
            return redirect()->route('staff.readings.index');
        });

        Route::get('/staff/sales/history', function () {
            return redirect()->route('staff.sales.history');
        });

        Route::get('/staff/pump-station', function () {
            return view('pages.staff.pumpstation', ['title' => 'Pump Station']);
        })->name('staff.pump.index');

        Route::post('/staff/pump-station/store', function () {
            return back()->with('status', 'Pump station saving is not connected yet.');
        })->name('staff.pump.store');

        Route::post('/staff/pump-station/{id}/update', function ($id) {
            return back()->with('status', "Pump station update {$id} is not connected yet.");
        })->name('staff.pump.update');

        Route::match(['get', 'post'], '/staff/pump-station/print-selected', [PrintedTransactionController::class, 'printSelected'])
            ->name('staff.pump.printSelected');

        Route::get('/staff/shift-readings', function () {
            return view('pages.staff.shiftreadings', ['title' => 'Shift Readings']);
        })->name('staff.readings.index');

        Route::post('/staff/shift-readings/store', function () {
            return back()->with('status', 'Shift reading saving is not connected yet.');
        })->name('staff.readings.store');

        Route::get('/staff/readings/{reading}/images/{dispenser}/{type}', function ($reading, $dispenser, $type) {
            abort(404, "Image {$type} for reading {$reading} is not connected yet.");
        })
            ->where(['dispenser' => '[1-3]', 'type' => 'liters|sales'])
            ->name('staff.readings.image');

        Route::get('/staff/readings/{id}/details', function ($id) {
            return response()->json([
                'message' => 'Shift reading details are not connected yet.',
                'id' => $id,
            ], 501);
        })->name('staff.readings.details');

        Route::get('/staff/sales-history', function () {
            return view('pages.staff.transactionhistory', ['title' => 'Transaction History']);
        })->name('staff.sales.history');

        Route::get('/staff/sales-history/print', function () {
            return view('pages.staff.transactionhistory', ['title' => 'Print Transaction History']);
        })->name('staff.sales.print');

        Route::prefix('staff/printed-transactions')->name('staff.printed-transactions.')->group(function () {
            Route::get('/', [PrintedTransactionController::class, 'index'])->name('index');
            Route::post('/', [PrintedTransactionController::class, 'storeSelected'])->name('store');
            Route::get('/{batch}/print', [PrintedTransactionController::class, 'printBatch'])->name('print');
            Route::post('/{batch}/mark', [PrintedTransactionController::class, 'mark'])->name('mark');
        });

        Route::post('/staff/pump/void', function () {
            return back()->with('status', 'Pump voiding is not connected yet.');
        })->name('staff.pump.void');

        Route::post('/staff/pump/payment', function () {
            return back()->with('status', 'Pump payment posting is not connected yet.');
        })->name('staff.pump.storePayment');

        Route::post('/staff/pump/payment/bypass', function () {
            return back()->with('status', 'Pump payment bypass is not connected yet.');
        })->name('staff.pump.bypassPayment');

        Route::post('/staff/pump/payment/void-posted', function () {
            return back()->with('status', 'Posted payment voiding is not connected yet.');
        })->name('staff.pump.voidPostedPayment');
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









