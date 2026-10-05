<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PreparationController;
use App\Http\Controllers\ImportExcelController;
use App\Http\Controllers\LpConfigController;
use App\Http\Controllers\ImportTmminController;
use App\Http\Controllers\ImportAdmController;
use App\Http\Controllers\AdmLeadTimeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\MilkrunController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RunningTextController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\KanbanTmminsController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\KanbanHpmController;
use App\Http\Controllers\HpmAddressController;
use App\Http\Controllers\SlipHpmController;
use App\Http\Controllers\PullingMatrixController;
use App\Http\Controllers\AdmaddressController; 
use App\Http\Controllers\KanbanadmController;
use App\Http\Controllers\ArsAdmController;
use App\Http\Controllers\KanbanAdmSplitController;
use App\Http\Controllers\AdmAddressControllerv2;
use App\Http\Controllers\ShippingMatrixController;
use App\Http\Controllers\PrepMonitoringController;
use App\Http\Controllers\NtcAddressController;
use App\Http\Controllers\AddressFjiController;
use App\Http\Controllers\AddressFutabaController;
use App\Http\Controllers\KanbanFutabaSplitController;
use App\Http\Controllers\AddressHinoController;
use App\Http\Controllers\KanbanHinoSplitController;
use App\Http\Controllers\KanbanBankTgiController;
use App\Http\Controllers\KanbanTgiSplitController;
use App\Http\Controllers\AddressTgiController;
use App\Http\Controllers\KanbanTmminsP2Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('login'));

/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {

    Route::prefix('token')->name('api.token.')->group(function () {
        Route::post('/validate',   [TokenController::class, 'validateToken'])  ->name('validate');
        Route::get('/is-expired',  [TokenController::class, 'isSystemExpired'])->name('is-expired');
        Route::get('/expiry',      [TokenController::class, 'getSystemExpiry'])->name('expiry');
    });

    Route::get('/advertisements/current', [AdvertisementController::class, 'checkCurrentAd'])
        ->name('api.advertisements.current');
});

/*
|--------------------------------------------------------------------------
| Andon (public, no auth required)
|--------------------------------------------------------------------------
*/
Route::prefix('andon')->name('andon.')->group(function () {
    Route::get('/preparations',      [PreparationController::class, 'andon'])        ->name('preparations');
    Route::get('/shippings',         [ShippingController::class,    'andon'])        ->name('shippings');
    Route::get('/shippings-group',   [ShippingController::class,    'andonReverse']) ->name('shippings.group');
    Route::get('/deliveries',        [DeliveryController::class,    'andon'])        ->name('deliveries');
    Route::get('/deliveries/group',  [DeliveryController::class,    'andonReverse']) ->name('deliveries.group');
    Route::get('/milkruns',          [MilkrunController::class,     'andon'])        ->name('milkruns');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | Dashboard
    |----------------------------------------------------------------------
    */
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Profile (Breeze)
    |----------------------------------------------------------------------
    */
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',    [ProfileController::class, 'edit'])   ->name('edit');
        Route::patch('/',  [ProfileController::class, 'update']) ->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Users
    |----------------------------------------------------------------------
    */
    Route::resource('users', UserController::class);

    /*
    |----------------------------------------------------------------------
    | Preparations
    |----------------------------------------------------------------------
    */
    Route::delete('/preparations/delete-all', [PreparationController::class,  'deleteAll'])->name('preparations.deleteAll');
    Route::get('/preparations/find-by-dn',    [PreparationController::class,  'findByDn']) ->name('preparations.findByDn');
    Route::get('/preparations/scan',          [PreparationController::class,  'scan'])     ->name('preparations.scan');

    Route::post('/preparations/import',       [ImportExcelController::class,  'import'])   ->name('preparations.import');
    Route::post('/preparations/import-tmmin', [ImportTmminController::class,  'import'])   ->name('preparations.import-tmmin');
    Route::post('/preparations/import-adm',   [ImportAdmController::class,    'import'])   ->name('preparations.import-adm');

    Route::get('/import-excel/download-template', [ImportExcelController::class, 'downloadTemplate'])
        ->name('import-excel.download-template');

    Route::resource('preparations', PreparationController::class);

    /*
    |----------------------------------------------------------------------
    | LP Config
    |----------------------------------------------------------------------
    */
    Route::resource('lp-configs', LpConfigController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('lp-configs/batch-save', [LpConfigController::class, 'batchSave'])->name('lp-configs.batch-save');

    Route::get('/shipping-matrix', [ShippingMatrixController::class, 'index'])->name('shipping-matrix.index');
Route::post('/shipping-matrix/batch-save', [ShippingMatrixController::class, 'batchSave'])->name('shipping-matrix.batch-save');

    /*
    |----------------------------------------------------------------------
    | ADM Lead Time
    |----------------------------------------------------------------------
    */
    Route::prefix('adm-lead-time')->name('adm-lead-time.')->group(function () {
        Route::get('/',            [AdmLeadTimeController::class, 'index'])    ->name('index');
        Route::post('/',           [AdmLeadTimeController::class, 'store'])    ->name('store');
        Route::post('/batch-save', [AdmLeadTimeController::class, 'batchSave'])->name('batch-save');
        Route::put('/{id}',        [AdmLeadTimeController::class, 'update'])   ->name('update');
        Route::delete('/{id}',     [AdmLeadTimeController::class, 'destroy'])  ->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Pulling Matrix
    |----------------------------------------------------------------------
    */
    Route::prefix('pulling-matrix')->name('pulling-matrix.')->group(function () {
        Route::get('/',            [PullingMatrixController::class, 'index'])    ->name('index');
        Route::post('/',           [PullingMatrixController::class, 'store'])    ->name('store');
        Route::post('/batch-save', [PullingMatrixController::class, 'batchSave'])->name('batch-save');
        Route::put('/{id}',        [PullingMatrixController::class, 'update'])   ->name('update');
        Route::delete('/{id}',     [PullingMatrixController::class, 'destroy'])  ->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Shippings
    |----------------------------------------------------------------------
    */
    Route::prefix('shippings')->name('shippings.')->group(function () {
        Route::get('/',         [ShippingController::class, 'index'])       ->name('index');
        Route::get('/reverse',  [ShippingController::class, 'indexReverse'])->name('indexReverse');
        Route::get('/checking-lp', [ShippingController::class, 'checkingLp'])->name('checkingLp');

        Route::delete('/delete-all',              [ShippingController::class, 'deleteAll'])            ->name('deleteAll');
        Route::post('/move-from-preparation',     [ShippingController::class, 'moveFromPreparation'])  ->name('moveFromPreparation');
        Route::post('/move-to-delivery',          [ShippingController::class, 'moveToDelivery'])       ->name('moveToDelivery');
        Route::post('/scan-to-delivery',          [ShippingController::class, 'scanToDelivery'])       ->name('scanToDelivery');
        Route::post('/move-to-delivery-by-route', [ShippingController::class, 'moveToDeliveryByRoute'])->name('moveToDeliveryByRoute');
        Route::post('/check-route',               [ShippingController::class, 'checkRoute'])           ->name('checkRoute');
        Route::post('/scan-route',                [ShippingController::class, 'scanRoute'])            ->name('scanRoute');
        Route::get('/get-by-route',               [ShippingController::class, 'getByRoute'])           ->name('getByRoute');
        Route::get('/find-by-dn',                 [ShippingController::class, 'findByDn'])             ->name('findByDn');

        Route::get('/{shipping}/edit', [ShippingController::class, 'edit'])   ->name('edit');
        Route::put('/{shipping}',      [ShippingController::class, 'update']) ->name('update');
        Route::delete('/{shipping}',   [ShippingController::class, 'destroy'])->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Deliveries
    |----------------------------------------------------------------------
    */
    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/',        [DeliveryController::class, 'index'])       ->name('index');
        Route::get('/reverse', [DeliveryController::class, 'indexReverse'])->name('indexReverse');

        Route::delete('/delete-all',         [DeliveryController::class, 'deleteAll'])      ->name('deleteAll');
        Route::post('/move-from-shipping',   [DeliveryController::class, 'moveFromShipping'])->name('moveFromShipping');
        Route::post('/move-by-route',        [DeliveryController::class, 'moveByRoute'])     ->name('moveByRoute');
        Route::get('/find-by-dn',            [DeliveryController::class, 'findByDn'])        ->name('findByDn');
        Route::get('/delay-data',            [DeliveryController::class, 'getDelayData'])    ->name('getDelayData');

        Route::get('/{delivery}/edit',        [DeliveryController::class, 'edit'])         ->name('edit');
        Route::put('/{delivery}',             [DeliveryController::class, 'update'])       ->name('update');
        Route::delete('/{delivery}',          [DeliveryController::class, 'destroy'])      ->name('destroy');
        Route::patch('/{delivery}/status',    [DeliveryController::class, 'updateStatus']) ->name('updateStatus');
    });

    /*
    |----------------------------------------------------------------------
    | Milkruns
    |----------------------------------------------------------------------
    */
    Route::prefix('milkruns')->name('milkruns.')->group(function () {
        Route::get('/', [MilkrunController::class, 'index'])->name('index');

        Route::delete('/delete-all',   [MilkrunController::class, 'deleteAll'])  ->name('deleteAll');
        Route::post('/scan-arrival',   [MilkrunController::class, 'scanArrival'])->name('scanArrival');
        Route::get('/delay-data',      [MilkrunController::class, 'getDelayData'])->name('getDelayData');
        Route::get('/export',          [MilkrunController::class, 'exportExcel'])->name('export'); // <-- fix di sini

        Route::get('/{milkrun}/dns',        [MilkrunController::class, 'getDnList'])       ->name('dns');
        Route::get('/{milkrun}/edit',       [MilkrunController::class, 'edit'])            ->name('edit');
        Route::put('/{milkrun}',            [MilkrunController::class, 'update'])          ->name('update');
        Route::delete('/{milkrun}',         [MilkrunController::class, 'destroy'])         ->name('destroy');
        Route::patch('/{milkrun}/arrival',  [MilkrunController::class, 'updateArrival'])   ->name('updateArrival');
        Route::patch('/{milkrun}/departure',[MilkrunController::class, 'updateDeparture']) ->name('updateDeparture');
    });

    /*
    |----------------------------------------------------------------------
    | Histories
    |----------------------------------------------------------------------
    */
    Route::prefix('histories')->name('histories.')->group(function () {
        Route::get('/', [HistoryController::class, 'index'])->name('index');

        Route::delete('/delete-all',    [HistoryController::class, 'deleteAll'])   ->name('deleteAll');
        Route::post('/scan-to-history', [HistoryController::class, 'scanToHistory'])->name('scanToHistory');
        Route::get('/export',           [HistoryController::class, 'export'])      ->name('export'); // <-- BARU: export excel

        Route::get('/{history}',       [HistoryController::class, 'show'])   ->name('show');
        Route::delete('/{history}',    [HistoryController::class, 'destroy'])->name('destroy');
        Route::get('/{history}/print', [HistoryController::class, 'print'])  ->name('print');
    });

    /*
    |----------------------------------------------------------------------
    | Kanban TMMIN
    |----------------------------------------------------------------------
    */
    Route::prefix('kanbantmmins')->name('kanbantmmins.')->group(function () {
        Route::get('/',              [KanbanTmminsController::class, 'index'])            ->name('index');
        Route::get('/by-dn',         [KanbanTmminsController::class, 'indexByDn'])        ->name('indexByDn');
        Route::get('/plant-counts',  [KanbanTmminsController::class, 'getPlantCounts'])   ->name('getPlantCounts');
        Route::get('/printall',      [KanbanTmminsController::class, 'printAll'])         ->name('printall');
        Route::get('/print-selected',[KanbanTmminsController::class, 'printSelected'])    ->name('printselected');
        Route::get('/print-group',   [KanbanTmminsController::class, 'printGroup'])       ->name('printgroup');
        Route::get('/plant-counts-by-ids', [KanbanTmminsController::class, 'getPlantCountsByIds'])->name('plantcountsbyids');

        Route::post('/import',       [KanbanTmminsController::class, 'importTxt'])        ->name('import');

        Route::get('/print/{id}',    [KanbanTmminsController::class, 'print'])            ->name('print');
        Route::delete('/destroy-group/{manifest_no}', [KanbanTmminsController::class, 'destroyGroup'])
            ->where('manifest_no', '.*')
            ->name('destroygroup');
        Route::delete('/{id}',       [KanbanTmminsController::class, 'destroy'])          ->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Kanban HPM
    |----------------------------------------------------------------------
    */
    Route::prefix('kanbanhpms')->name('kanbanhpms.')->group(function () {
        Route::get('/',              [KanbanHpmController::class, 'index'])         ->name('index');
        Route::get('/printall',      [KanbanHpmController::class, 'printAll'])      ->name('printall');
        Route::get('/filter-options',[KanbanHpmController::class, 'filterOptions']) ->name('filterOptions'); // ← INI
        Route::match(['get', 'post'], '/print-filtered', [KanbanHpmController::class, 'printFiltered'])->name('printFiltered');

        Route::post('/import',       [KanbanHpmController::class, 'importTxt'])     ->name('import');
        Route::post('/adjust-weekly',[KanbanHpmController::class, 'adjustWeekly'])  ->name('adjustWeekly');

        Route::delete('/{id}',       [KanbanHpmController::class, 'destroy'])       ->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Slip HPM
    |----------------------------------------------------------------------
    */
    Route::prefix('sliphpms')->name('sliphpms.')->group(function () {
        Route::get('/',               [SlipHpmController::class, 'index'])         ->name('index');
        Route::get('/print-filtered', [SlipHpmController::class, 'printFiltered']) ->name('printFiltered');

        Route::post('/import',        [SlipHpmController::class, 'import'])        ->name('import');

        Route::delete('/{sliphpm}',   [SlipHpmController::class, 'destroy'])       ->name('destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Advertisements
    |----------------------------------------------------------------------
    */
    Route::prefix('advertisements')->name('advertisements.')->group(function () {
        Route::get('/',                      [AdvertisementController::class, 'index'])       ->name('index');
        Route::post('/',                     [AdvertisementController::class, 'store'])       ->name('store');
        Route::get('/{advertisement}/edit',  [AdvertisementController::class, 'edit'])        ->name('edit');
        Route::put('/{advertisement}',       [AdvertisementController::class, 'update'])      ->name('update');
        Route::delete('/{advertisement}',    [AdvertisementController::class, 'destroy'])     ->name('destroy');
        Route::post('/{advertisement}/toggle',[AdvertisementController::class, 'toggleActive'])->name('toggle');
    });

    /*
    |----------------------------------------------------------------------
    | Addresses
    |----------------------------------------------------------------------
    */
    Route::prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/import',       [AddressController::class, 'importPage']) ->name('import.page');
        Route::post('/import',      [AddressController::class, 'import'])     ->name('import');
        Route::post('/import-rack', [AddressController::class, 'importRack']) ->name('import-rack');
    });
    Route::resource('addresses', AddressController::class)->except(['show']);

    /*
    |----------------------------------------------------------------------
    | ADM Addresses
    |----------------------------------------------------------------------
    */
    Route::delete('admaddresses/delete-all', [AdmaddressController::class, 'deleteAll'])->name('admaddresses.deleteAll');
    Route::post('admaddresses/import',       [AdmaddressController::class, 'import'])    ->name('admaddresses.import');
    Route::resource('admaddresses', AdmaddressController::class)->except(['show', 'create']);

    /*
    |----------------------------------------------------------------------
    | HPM Addresses
    |----------------------------------------------------------------------
    */
    Route::prefix('hpm-addresses')->name('hpm-addresses.')->group(function () {
        Route::get('/',                    [HpmAddressController::class, 'index'])  ->name('index');
        Route::post('/',                   [HpmAddressController::class, 'store'])  ->name('store');
        Route::get('/{hpmAddress}/edit',   [HpmAddressController::class, 'edit'])   ->name('edit');
        Route::put('/{hpmAddress}',        [HpmAddressController::class, 'update']) ->name('update');
        Route::delete('/{hpmAddress}',     [HpmAddressController::class, 'destroy'])->name('destroy');
        Route::post('/import',             [HpmAddressController::class, 'import']) ->name('import');
    });

    /*
    |----------------------------------------------------------------------
    | Running Text
    |----------------------------------------------------------------------
    */
    Route::prefix('running-text')->name('running-text.')->group(function () {
        Route::get('/data',    [RunningTextController::class, 'getData'])->name('data');
        Route::post('/update', [RunningTextController::class, 'update']) ->name('update');
    });
    
     /*
    |----------------------------------------------------------------------
    | Kanban ADM
    |----------------------------------------------------------------------
    */
    Route::prefix('kanbanadms')->name('kanbanadms.')->group(function () {
        Route::get('/',               [KanbanadmController::class, 'index'])         ->name('index');
        Route::get('/printall',       [KanbanadmController::class, 'printAll'])      ->name('printall');
        Route::get('/print-selected', [KanbanadmController::class, 'printSelected']) ->name('printselected');
        Route::post('/import',        [KanbanadmController::class, 'import'])        ->name('import');
        Route::delete('/{id}',        [KanbanadmController::class, 'destroy'])       ->name('destroy');
    });

    Route::prefix('arsadms')->name('arsadms.')->group(function () {
        Route::get('/',              [ArsAdmController::class, 'index'])->name('index');
        Route::post('/',             [ArsAdmController::class, 'store'])->name('store');
        Route::get('/{arsadm}/edit', [ArsAdmController::class, 'edit'])->name('edit');
        Route::put('/{arsadm}',      [ArsAdmController::class, 'update'])->name('update');
        Route::delete('/delete-all', [ArsAdmController::class, 'deleteAll'])->name('deleteAll');
        Route::delete('/{arsadm}',   [ArsAdmController::class, 'destroy'])->name('destroy');
        Route::post('/import',       [ArsAdmController::class, 'import'])->name('import');
    });

    Route::get('/split-kanban', [KanbanAdmSplitController::class, 'index'])->name('kanban-split.index');
    Route::post('/split-kanban', [KanbanAdmSplitController::class, 'process'])->name('kanban-split.process');
    Route::get('/split-kanban/meta/{token}', [KanbanAdmSplitController::class, 'labelsMeta'])
        ->name('kanban-split.labels-meta');
    Route::get('/split-kanban/recent', [KanbanAdmSplitController::class, 'recent'])
        ->name('kanban-split.recent');
    Route::get('/split-kanban/{token}/download', [KanbanAdmSplitController::class, 'download'])
        ->name('kanban-split.download');

    Route::get('/admadressesv2', [AdmAddressControllerv2::class, 'index'])->name('admadressesv2.index');
    Route::post('/admadressesv2', [AdmAddressControllerv2::class, 'store'])->name('admadressesv2.store');
    Route::get('/admadressesv2/{admaddressv2}/edit', [AdmAddressControllerv2::class, 'edit'])->name('admadressesv2.edit');
    Route::put('/admadressesv2/{admaddressv2}', [AdmAddressControllerv2::class, 'update'])->name('admadressesv2.update');
    Route::delete('/admadressesv2/{admaddressv2}', [AdmAddressControllerv2::class, 'destroy'])->name('admadressesv2.destroy');
    Route::delete('/admadressesv2/delete-all', [AdmAddressControllerv2::class, 'deleteAll'])->name('admadressesv2.deleteAll');
    Route::post('/admadressesv2/import', [AdmAddressControllerv2::class, 'import'])->name('admadressesv2.import');

    Route::get('/prep-monitoring', [PrepMonitoringController::class, 'index'])->name('prep-monitoring.index');
    Route::get('/prep-monitoring/check-dn', [PrepMonitoringController::class, 'checkDn'])->name('prep-monitoring.check-dn');
    Route::post('/prep-monitoring/scan', [PrepMonitoringController::class, 'scan'])->name('prep-monitoring.scan');
    Route::post('prep-monitoring/scan-direct', [App\Http\Controllers\PrepMonitoringController::class, 'scanDirect'])
    ->name('prep-monitoring.scan-direct');
    Route::delete('/prep-monitoring/delete-all', [PrepMonitoringController::class, 'deleteAll'])->name('prep-monitoring.delete-all');
    Route::get('/andon/prep-monitoring', [PrepMonitoringController::class, 'andon'])
    ->name('andon.prep-monitoring');

    Route::get('/ntc-addresses', [\App\Http\Controllers\NtcAddressController::class, 'index'])->name('ntcaddresses.index');
    Route::post('/ntc-addresses', [\App\Http\Controllers\NtcAddressController::class, 'store'])->name('ntcaddresses.store');
    Route::get('/ntc-addresses/{ntcAddress}/edit', [\App\Http\Controllers\NtcAddressController::class, 'edit'])->name('ntcaddresses.edit');
    Route::put('/ntc-addresses/{ntcAddress}', [\App\Http\Controllers\NtcAddressController::class, 'update'])->name('ntcaddresses.update');
    Route::delete('/ntc-addresses/{ntcAddress}', [\App\Http\Controllers\NtcAddressController::class, 'destroy'])->name('ntcaddresses.destroy');
    Route::delete('/ntc-addresses-delete-all', [\App\Http\Controllers\NtcAddressController::class, 'deleteAll'])->name('ntcaddresses.deleteAll');
    Route::post('/ntc-addresses/import', [\App\Http\Controllers\NtcAddressController::class, 'import'])->name('ntcaddresses.import');

    Route::prefix('kanban-ntc-split')->name('kanban-ntc-split.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KanbanNtcSplitController::class, 'index'])->name('index');
        Route::post('/process', [\App\Http\Controllers\KanbanNtcSplitController::class, 'process'])->name('process');
        Route::get('/recent', [\App\Http\Controllers\KanbanNtcSplitController::class, 'recent'])->name('recent');
        Route::get('/{token}/labels-meta', [\App\Http\Controllers\KanbanNtcSplitController::class, 'labelsMeta'])->name('labels-meta');
        Route::get('/{token}/download', [\App\Http\Controllers\KanbanNtcSplitController::class, 'download'])->name('download');
    });

    Route::get('/address-fji', [\App\Http\Controllers\AddressFjiController::class, 'index'])->name('addressfji.index');
    Route::post('/address-fji', [\App\Http\Controllers\AddressFjiController::class, 'store'])->name('addressfji.store');
    Route::get('/address-fji/{addressFji}/edit', [\App\Http\Controllers\AddressFjiController::class, 'edit'])->name('addressfji.edit');
    Route::put('/address-fji/{addressFji}', [\App\Http\Controllers\AddressFjiController::class, 'update'])->name('addressfji.update');
    Route::delete('/address-fji/{addressFji}', [\App\Http\Controllers\AddressFjiController::class, 'destroy'])->name('addressfji.destroy');
    Route::delete('/address-fji-delete-all', [\App\Http\Controllers\AddressFjiController::class, 'deleteAll'])->name('addressfji.deleteAll');
    Route::post('/address-fji/import', [\App\Http\Controllers\AddressFjiController::class, 'import'])->name('addressfji.import');

    Route::prefix('kanban-fji-split')->name('kanban-fji-split.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KanbanFjiSplitController::class, 'index'])->name('index');
        Route::post('/process', [\App\Http\Controllers\KanbanFjiSplitController::class, 'process'])->name('process');
        Route::get('/recent', [\App\Http\Controllers\KanbanFjiSplitController::class, 'recent'])->name('recent');
        Route::get('/{token}/labels-meta', [\App\Http\Controllers\KanbanFjiSplitController::class, 'labelsMeta'])->name('labels-meta');
        Route::get('/{token}/download', [\App\Http\Controllers\KanbanFjiSplitController::class, 'download'])->name('download');
    });

    Route::get('/address-futaba', [\App\Http\Controllers\AddressFutabaController::class, 'index'])->name('addressfutaba.index');
    Route::post('/address-futaba', [\App\Http\Controllers\AddressFutabaController::class, 'store'])->name('addressfutaba.store');
    Route::get('/address-futaba/{addressFutaba}/edit', [\App\Http\Controllers\AddressFutabaController::class, 'edit'])->name('addressfutaba.edit');
    Route::put('/address-futaba/{addressFutaba}', [\App\Http\Controllers\AddressFutabaController::class, 'update'])->name('addressfutaba.update');
    Route::delete('/address-futaba/{addressFutaba}', [\App\Http\Controllers\AddressFutabaController::class, 'destroy'])->name('addressfutaba.destroy');
    Route::delete('/address-futaba-delete-all', [\App\Http\Controllers\AddressFutabaController::class, 'deleteAll'])->name('addressfutaba.deleteAll');
    Route::post('/address-futaba/import', [\App\Http\Controllers\AddressFutabaController::class, 'import'])->name('addressfutaba.import');

    Route::prefix('kanban-futaba-split')->name('kanban-futaba-split.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KanbanFutabaSplitController::class, 'index'])->name('index');
        Route::post('/process', [\App\Http\Controllers\KanbanFutabaSplitController::class, 'process'])->name('process');
        Route::get('/recent', [\App\Http\Controllers\KanbanFutabaSplitController::class, 'recent'])->name('recent');
        Route::get('/{token}/labels-meta', [\App\Http\Controllers\KanbanFutabaSplitController::class, 'labelsMeta'])->name('labels-meta');
        Route::get('/{token}/download', [\App\Http\Controllers\KanbanFutabaSplitController::class, 'download'])->name('download');
    });

    Route::get('/address-hino', [\App\Http\Controllers\AddressHinoController::class, 'index'])->name('addresshino.index');
    Route::post('/address-hino', [\App\Http\Controllers\AddressHinoController::class, 'store'])->name('addresshino.store');
    Route::get('/address-hino/{addressHino}/edit', [\App\Http\Controllers\AddressHinoController::class, 'edit'])->name('addresshino.edit');
    Route::put('/address-hino/{addressHino}', [\App\Http\Controllers\AddressHinoController::class, 'update'])->name('addresshino.update');
    Route::delete('/address-hino/{addressHino}', [\App\Http\Controllers\AddressHinoController::class, 'destroy'])->name('addresshino.destroy');
    Route::delete('/address-hino-delete-all', [\App\Http\Controllers\AddressHinoController::class, 'deleteAll'])->name('addresshino.deleteAll');
    Route::post('/address-hino/import', [\App\Http\Controllers\AddressHinoController::class, 'import'])->name('addresshino.import');

    Route::prefix('kanban-hino-split')->name('kanban-hino-split.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KanbanHinoSplitController::class, 'index'])->name('index');
        Route::post('/process', [\App\Http\Controllers\KanbanHinoSplitController::class, 'process'])->name('process');
        Route::get('/recent', [\App\Http\Controllers\KanbanHinoSplitController::class, 'recent'])->name('recent');
        Route::get('/{token}/labels-meta', [\App\Http\Controllers\KanbanHinoSplitController::class, 'labelsMeta'])->name('labels-meta');
        Route::get('/{token}/download', [\App\Http\Controllers\KanbanHinoSplitController::class, 'download'])->name('download');
    });

    Route::get('/kanban-bank-tgi', [\App\Http\Controllers\KanbanBankTgiController::class, 'index'])->name('kanbanbanktgi.index');
    Route::post('/kanban-bank-tgi/upload', [\App\Http\Controllers\KanbanBankTgiController::class, 'upload'])->name('kanbanbanktgi.upload');
    Route::delete('/kanban-bank-tgi/{kanbanBankTgi}', [\App\Http\Controllers\KanbanBankTgiController::class, 'destroy'])->name('kanbanbanktgi.destroy');

    Route::prefix('kanban-tgi-split')->name('kanban-tgi-split.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KanbanTgiSplitController::class, 'index'])->name('index');
        Route::post('/process', [\App\Http\Controllers\KanbanTgiSplitController::class, 'process'])->name('process');
        Route::post('/print-filtered', [\App\Http\Controllers\KanbanTgiSplitController::class, 'printFiltered'])->name('print-filtered');
        Route::get('/recent', [\App\Http\Controllers\KanbanTgiSplitController::class, 'recent'])->name('recent');
        Route::get('/{token}/items-meta', [\App\Http\Controllers\KanbanTgiSplitController::class, 'itemsMeta'])->name('items-meta');
        Route::get('/{token}/download', [\App\Http\Controllers\KanbanTgiSplitController::class, 'download'])->name('download');
    });

    Route::get('/address-tgi', [\App\Http\Controllers\AddressTgiController::class, 'index'])->name('addresstgi.index');
    Route::post('/address-tgi', [\App\Http\Controllers\AddressTgiController::class, 'store'])->name('addresstgi.store');
    Route::get('/address-tgi/{addressTgi}/edit', [\App\Http\Controllers\AddressTgiController::class, 'edit'])->name('addresstgi.edit');
    Route::put('/address-tgi/{addressTgi}', [\App\Http\Controllers\AddressTgiController::class, 'update'])->name('addresstgi.update');
    Route::delete('/address-tgi/{addressTgi}', [\App\Http\Controllers\AddressTgiController::class, 'destroy'])->name('addresstgi.destroy');
    Route::delete('/address-tgi-delete-all', [\App\Http\Controllers\AddressTgiController::class, 'deleteAll'])->name('addresstgi.deleteAll');
    Route::post('/address-tgi/import', [\App\Http\Controllers\AddressTgiController::class, 'import'])->name('addresstgi.import');

    Route::prefix('kanbantmminsp2')
    ->name('kanbantmminsp2.')
    ->controller(KanbanTmminsP2Controller::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/by-dn', 'indexByDn')->name('indexByDn');
        Route::post('/import', 'importTxt')->name('import');
        Route::get('/print-all', 'printAll')->name('printall');
        Route::get('/print-selected', 'printSelected')->name('printselected');
        Route::get('/print-group', 'printGroup')->name('printgroup');
        Route::get('/plant-counts', 'getPlantCounts')->name('plantcounts');
        Route::get('/plant-counts-by-ids', 'getPlantCountsByIds')->name('plantcountsbyids');
        Route::delete('/group/{manifest_no}', 'destroyGroup')->name('destroyGroup');
        Route::get('/{id}/print', 'print')->name('print');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });
}); // end middleware auth

require __DIR__.'/auth.php';