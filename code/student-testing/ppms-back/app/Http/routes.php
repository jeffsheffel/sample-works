<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// V1 API
Route::group(['prefix' => 'api/v1', 'namespace' => 'Api\V1'], function()
{
    // accommodated orders
    Route::get('accommodatedorder', 'AccommodatedOrderController@collection');
    Route::post('accommodatedorder/create', 'AccommodatedOrderController@create');
    Route::post('accommodatedorder/create-manual/{CUST_ID}', 'AccommodatedOrderController@createManual');
    Route::get('accommodatedorder/pull-admin/{CUST_ID}', 'AccommodatedOrderController@pullAdmin');
    Route::get('accommodatedorder/generate-xls/{id}', 'AccommodatedOrderController@generateXls');
    Route::get('accommodatedorder/{id}', 'AccommodatedOrderController@show');

    // combined orders
    Route::post('combinedorder/create', 'CombinedOrderController@create');
    
    // courseavailability
    Route::get('courseavailability', 'CourseAvailabilityController@collection');
    Route::get('courseavailability/sftp', 'CourseAvailabilityController@sftp');
    Route::get('courseavailability/check', 'CourseAvailabilityController@check');
    Route::get('courseavailability/importCourseAvailabilityFile', 'CourseAvailabilityController@importCourseAvailabilityFile');
    Route::get('courseavailability/updateQcAdminCourses', 'CourseAvailabilityController@updateQcAdminCourses');
    Route::get('courseavailability/updateQcAdminData', 'CourseAvailabilityController@updateQcAdminData');
    Route::get('courseavailability/{id}', 'CourseAvailabilityController@show');

    // customerservice
    Route::get('customerservice/download-data', 'CustomerServiceController@downloadData');
    Route::post('customerservice/search', 'CustomerServiceController@search');
    Route::post('customerservice/get-school-info', 'CustomerServiceController@getSchoolInfo');
    Route::post('customerservice/get-classes/{CUST_ID}/{schoolId}', 'CustomerServiceController@getClasses');
    Route::post('customerservice/get-students/{CUST_ID}/{schoolId}', 'CustomerServiceController@getStudents');

    // dashboard
    Route::get('dashboard', 'DashboardController@get');
    
    // handscoring
    Route::get('handscore', 'HandScoreController@collection');
    Route::post('handscore/create', 'HandScoreController@collection@create');
    Route::get('handscore/sftp', 'HandScoreController@sftp');
    Route::get('handscore/pull-admin/{CUST_ID}', 'HandScoreController@pullAdmin');
    Route::get('handscore/check', 'HandScoreController@check');
    Route::get('handscore/check-success', 'HandScoreController@checkSuccess');
    Route::get('handscore/check-error', 'HandScoreController@checkError');
    Route::get('handscore/updateQcAdminScores/{id}', 'HandScoreController@updateQcAdminScores');
    Route::get('handscore/fix-file/{id}', 'HandScoreController@getFixFile');
    Route::get('handscore/{id}', 'HandScoreController@show');
    
    // hierarchy
    Route::get('hierarchy', 'HierarchyController@collection');
    Route::get('hierarchy/sftp', 'HierarchyController@sftp');
    Route::get('hierarchy/check', 'HierarchyController@check');
    Route::get('hierarchy/updateQcAdminActTables', 'HierarchyController@updateQcAdminActTables');
    Route::get('hierarchy/{id}', 'HierarchyController@show');
    Route::get('hierarchydata', 'HierarchyDataController@collection');
    Route::get('hierarchydata/sftp', 'HierarchyDataController@sftp');
    Route::get('hierarchydata/check', 'HierarchyDataController@check');
    Route::get('hierarchydata/updateObject', 'HierarchyDataController@updateObject');
    Route::get('hierarchydata/{id}', 'HierarchyDataController@show');
    
    // multiple choice
    Route::get('multiplechoice', 'MultipleChoiceController@collection');
    Route::get('multiplechoice/sftp', 'MultipleChoiceController@sftp');
    Route::get('multiplechoice/check', 'MultipleChoiceController@check');
    Route::get('multiplechoice/check-success', 'MultipleChoiceController@checkSuccess');
    Route::get('multiplechoice/check-error', 'MultipleChoiceController@checkError');
    Route::get('multiplechoice/updateQcAdminScores/{id}', 'MultipleChoiceController@updateQcAdminScores');
    Route::get('multiplechoice/fix-file/{id}', 'MultipleChoiceController@getFixFile');
    Route::get('multiplechoice/{id}', 'MultipleChoiceController@show');
    
    // notifications
    Route::get('notification', 'NotificationController@collection');
    Route::get('notification/sftp', 'NotificationController@sftp');
    Route::get('notification/check', 'Notification@check');
    Route::get('notification/{id}', 'NotificationController@show');
    
    // print orders
    Route::get('printorder', 'PrintOrderController@collection');
    Route::post('printorder/create', 'PrintOrderController@create');
    Route::post('printorder/create-manual/{CUST_ID}', 'PrintOrderController@createManual');
    Route::get('printorder/sftp', 'PrintOrderController@sftp');
    Route::get('printorder/pull-admin/{CUST_ID}', 'PrintOrderController@pullAdmin');
    Route::get('printorder/check-success', 'PrintOrderController@checkSuccess');
    Route::get('printorder/check-error', 'PrintOrderController@checkError');
    Route::get('printorder/{id}', 'PrintOrderController@show');
    Route::post('printorder/log', 'PrintOrderController@log');
    //Route::get('printorder/generateXml/{id}', 'PrintOrderController@generateXml');
    //Route::get('printorder/files/pull', 'PrintOrderController@pull');
    //Route::get('printorder/files/get/{fileName}', 'PrintOrderController@getFile');
    //Route::get('printorder/files/put/{fileName}', 'PrintOrderController@putFile');

    // Admin API
    Route::get('qcadmin/{customer}/{classIds}', 'QCAdminController@getOrdersByClass');
    
    // users/auth
    Route::post('user/login', 'UserController@authenticate');
    Route::get('user/login', 'UserController@authenticate');
    Route::get('user/logout/{api_key}', 'UserController@deauthenticate');
});

// catch all, just displays version
Route::get('/', 'Api\V1\DashboardController@version');