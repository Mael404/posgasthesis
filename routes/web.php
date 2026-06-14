<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;
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

        Route::get('/admin/sales-monitoring', function () {
            return view('pages.admin.transactionlogs', ['title' => 'Transaction Logs']);
        })->name('admin.sales.index');

        Route::get('/admin/sales/print', function () {
            return view('pages.admin.transactionlogs', ['title' => 'Print Transaction Logs']);
        })->name('admin.sales.print');

        Route::prefix('admin/reconciliation')->name('admin.reconcile.')->group(function () {
            Route::get('/', function () {
                return view('pages.admin.shiftreconciliation', ['title' => 'Shift Reconciliation']);
            })->name('index');

            Route::get('/details/{id}', function ($id) {
                return response()->json([
                    'message' => 'Shift reconciliation details are not connected yet.',
                    'id' => $id,
                ], 501);
            })->name('details');

            Route::post('/approve', function () {
                return back()->with('status', 'Shift reconciliation approval is not connected yet.');
            })->name('approve');

            Route::post('/reject', function () {
                return back()->with('status', 'Shift reconciliation rejection is not connected yet.');
            })->name('reject');

            Route::post('/remarks', function () {
                return back()->with('status', 'Shift reconciliation remarks are not connected yet.');
            })->name('remarks');
        });

        Route::prefix('admin/printed-transactions')->name('admin.printed-transactions.')->group(function () {
            Route::get('/', function () {
                return view('pages.admin.printedtransactions', ['title' => 'Printed Transactions']);
            })->name('index');

            Route::get('/{batch}/print', function ($batch) {
                return view('pages.admin.printedtransactions', [
                    'title' => 'Print Transaction Batch',
                    'batch' => $batch,
                ]);
            })->name('print');

            Route::post('/{batch}/mark', function ($batch) {
                return back()->with('status', "Printed transaction batch {$batch} is not connected yet.");
            })->name('mark');
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

        Route::get('/inventory', function () {
            return view('pages.admin.inventoryandtanks', ['title' => 'Inventory & Tanks']);
        })->name('inventory.index');

        Route::get('/customers', function () {
            return view('pages.admin.customersandcredit', ['title' => 'Customers & Credit']);
        })->name('customers.index');

        Route::get('/admin/billing', function () {
            return view('pages.admin.billingandinvoices', ['title' => 'Billing & Invoices']);
        })->name('admin.billing.index');

        Route::get('/staffs', function () {
            return view('pages.admin.managestaffs', ['title' => 'Manage Staffs']);
        })->name('staffs.index');
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

        Route::get('/staff/pump-station/print-selected', function () {
            return redirect()->route('staff.printed-transactions.index');
        })->name('staff.pump.printSelected');

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
            Route::get('/', function () {
                return view('pages.staff.printedtransactions', ['title' => 'Printed Transactions']);
            })->name('index');

            Route::post('/', function () {
                return back()->with('status', 'Printed transaction saving is not connected yet.');
            })->name('store');

            Route::get('/{batch}/print', function ($batch) {
                return view('pages.staff.printedtransactions', [
                    'title' => 'Print Transaction Batch',
                    'batch' => $batch,
                ]);
            })->name('print');

            Route::post('/{batch}/mark', function ($batch) {
                return back()->with('status', "Printed transaction batch {$batch} is not connected yet.");
            })->name('mark');
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
















