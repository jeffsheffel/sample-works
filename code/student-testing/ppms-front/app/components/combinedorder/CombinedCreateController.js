(function () {
    'use strict';

    angular
        .module('pmet')
        .controller('CombinedorderCreateController', CombinedorderCreateController)
        .config(function ($stateProvider) {
            $stateProvider
                .state('combinedorder.create', {
                    url: "/create/:school_id?",
                    controller: 'CombinedorderCreateController as coc',
                    templateUrl: 'app/components/combinedorder/views/create.html',
                    data: {pageTitle: 'Create an Combined Order'}
                })
        });

    function CombinedorderCreateController($rootScope, $scope, $state, $stateParams, $document, CombinedorderFactory, SchoolService, StorageService, UtilityFactory, UtilityService) {
        var self = this;

        // public
        self.accommodated = [];
        self.print = [];
        self.placeOrder = placeOrder;
        self.getSchoolFormData = SchoolService.getSchoolFormData;
        self.addAccommodated = addAccommodated;
        self.addPrint = addPrint;
        self.removePrint = removePrint;
        self.removeAccommodated = removeAccommodated;
        self.filterCourses = filterCourses;
        self.getDataBySchoolId = getDataBySchoolId;
        self.formatDate = UtilityService.formatDate;
        self.updateTestingWindows = updateTestingWindows;
        self.populateSummaryReportingPeriods = SchoolService.populateSummaryReportingPeriods;
        self.shippingLevels = ['Ground', '3 Day Select', '2nd Day Air', 'Next Day Air', 'Next Day Air AM'];
        self.batteries = StorageService.get('battery');
        self.accommodationTypes = {'large_print' : 'Large Print', 'reader_script' : 'Reader Script', 'braile': 'Braille', 'audio_cd' : 'Audio CD'};
        self.schoolTestingWindow = [];
        self.hasTestingWindows = false;
        self.selectedSchool = null;
        self.customer = null;
        self.reportingTypes = null;
        self.order = addOrder();
        self.allReportingTypes = StorageService.get('summaryReportingPeriods');
        self.customers = StorageService.get('customer');

        // everything else is private
        init();

        if ($stateParams.school_id) {
            getDataBySchoolId($stateParams.school_id);
        }

        //dummyData(); // test data

        function init() {
           self.form =  self.form || $('#combinedorder-create-form');
           
           if (self.form.length) {
              $document.ready(function () {
                   // self.form.parsley();
                   // self.form.find('.date').datetimepicker({pickTime: false, defaultDate: new Date()});
                   // self.form.find('.phone').inputmask({mask: "(999) 999-9999"});

                   /*
                   // stupid hack
                   setTimeout(function(){
                        //self.form.find('[name="expedited"]').prop('checked', true);
                   }, 1000);
                    */
               });
           }
        }

        function getDataBySchoolId(schoolId, testingWindowId) {
            $('body').addClass('pace-running');
            var factory;
            
            if (undefined === testingWindowId) {
                factory = UtilityFactory.getSchoolInfo([schoolId])
            } else {
                factory = UtilityFactory.getSchoolInfoWithSRP([schoolId], testingWindowId);
            }
            
            factory.then(function (data) {
                log(data.data)

                if (data.data != '') {
                    self.school = data.data;
                    //log(self.school.qcAdmin.testing_windows);

                    self.order = {
                        expedited: 0,
                        contract_code: '',
                        testing_start_date: '',
                        testing_end_date: '',
                        school_id: self.school.id,
                        school_name: self.school.name,
                        district_id: self.school.district.id,
                        district_name: self.school.district.name,
                        reporting_type: self.reporting_type,
                        ship_level: 'Ground',
                        ship_to: '',
                        ship_address: '',
                        ship_address_2: '',
                        ship_address_3: '',
                        ship_city: '',
                        ship_state: '',
                        ship_zip: '',
                        ship_country: '',
                        ship_phone: '',
                        ship_email: '',
                    };

                    if (self.school.qcAdmin !== undefined) {
                        self.order.contact_first_name = self.school.qcAdmin.ship_first_name;
                        self.order.contact_last_name = self.school.qcAdmin.ship_last_name;
                        self.order.ship_to = self.school.qcAdmin.ship_to;
                        self.order.ship_address = self.school.qcAdmin.ship_address;
                        self.order.ship_address_2 = self.school.qcAdmin.ship_address2;
                        self.order.ship_city = self.school.qcAdmin.ship_city;
                        self.order.ship_state = self.school.qcAdmin.ship_state;
                        self.order.ship_zip = self.school.qcAdmin.ship_zip;
                        self.order.ship_country = self.school.qcAdmin.ship_country;
                        self.order.ship_phone = self.school.qcAdmin.ship_phone;
                        self.order.ship_email = self.school.qcAdmin.ship_email;

                        self.courses = self.school.qcAdmin.available_courses;
                        self.schoolTestingWindow = SchoolService.packageSchoolTestingWindows(self.school.qcAdmin.school_testing_windows);
                        self.hasTestingWindows = Object.keys(self.schoolTestingWindow).length !== 0;
                    }

                    self.selectedSchool = schoolId;
                } else {
                    Messenger().post({message: '<p>School ' + schoolId + ' not found</p>', type: 'error', hideAfter: 2});
                    self.selectedSchool = null;
                }
                
            }, function (error) {
                //displayError(error);
            }).finally(function(){
                $('body').removeClass('pace-running')
            });
        }

        function updateTestingWindows(testingWindowId) {
            
            var testingWindow = SchoolService.getTestingWindowById(self.school.qcAdmin.school_testing_windows, testingWindowId);
            log(testingWindow);
            if (testingWindow !== null) {
                self.order.testing_start_date = moment(UtilityService.formatDate(new Date(testingWindow.start_date))).format('MM/DD/YYYY');
                self.order.testing_end_date = moment(UtilityService.formatDate(new Date(testingWindow.end_date))).format('MM/DD/YYYY');
                self.testing_end_date_max = testingWindow.end_date;
            }
        }

        function filterCourses(battery) {
            return battery.distribution == self.customerType;
        }

        function placeOrder() {
            if (self.form.parsley().isValid()) {
                $('body').addClass('pace-running');
                CombinedorderFactory.placeOrder(self.order, self.print, self.accommodated)
                    .then(function (data) {
                        log(data.data);
                        var printOrderId = data.data.printOrderId || data.data.accommodatedOrderId.printOrderId;

                        if (data.data.printOrderId != null) {
                            Messenger().post({message: 'The Print Order has been created. The Print Order ID is ' + printOrderId + '.', type: 'success', hideAfter: 10});
                            $state.go('printorder.view', {print_order_id: printOrderId,});
                        }
                        if (data.data.accommodatedOrderId.printOrderId != null && data.data.accommodatedOrderId.id != null) {
                            Messenger().post({message: 'Both Orders have been created.  The Accommodated Order ID is ' + data.data.accommodatedOrderId.id + '. The Auto-Generated Print Order ID is ' + printOrderId + '.', type: 'success', hideAfter: 10});
                            $state.go('combinedorder.view', {print_order_id: printOrderId, accommodated_order_id: data.data.accommodatedOrderId.id});
                        }
                        //$scope.combinedorderCreateForm.$setPristine();

                    }, function (error) {
                        displayError(error);
                    }).finally(function(){
                        $('body').removeClass('pace-running')
                    });
            }
        }

        function displayError(error) {
             if (error.data.error && error.data.error.message) {
                var msg = [];

                angular.forEach(error.data.error.message, function(key, val){
                    msg.push(key[0]);
                });

                Messenger().post({message: '<p>' + msg.join('</p><p>') + '</p>', type: 'error', hideAfter: 2});
            }
        }

        function addOrder() {
            return {
                expedited: 0,
                contract_code: '',
                testing_start_date: '',
                testing_end_date: '',
                reporting_type: '',
                school_id: '',
                school_name: '',
                district_id: '',
                district_name: '',
                contact_first_name: '',
                contact_last_name: '',
                ship_level: 'Ground',
                ship_to: '',
                ship_address: '',
                ship_address_2: '',
                ship_address_3: '',
                ship_city: '',
                ship_state: '',
                ship_zip: '',
                ship_country: '',
                ship_phone: '',
                ship_email: '',
            };
        }

        function addAccommodated() {
            self.accommodated.push({
                battery_code: null,
                type: null,
                quantity: null,
            });
        }

        function removeAccommodated(index) {
            self.accommodated.splice(index, 1);
        }

        function addPrint() {
            self.print.push({
                battery_code: null,
                quantity: null,
            });
        }

        function removePrint(index) {
            self.print.splice(index, 1);
        }

        function dummyData() {
           self.order = {
                expedited: 1,
                contract_code: '123',
                testing_start_date: '1/1/2015',
                testing_end_date: '12/31/2015',
                school_id: '000001',
                school_name: 'ETOWAH HIGH SCHOOL',
                district_id: '000001',
                district_name: 'Sesame Street',
                contact_first_name: 'Run',
                contact_last_name: 'DMC',
                ship_to: 'Jam Master Jay',
                ship_level: 'Ground',
                ship_address: '1819 Denver West Drive',
                ship_address_2: 'Suite 350',
                ship_address_3: '',
                ship_city: 'Lakewood',
                ship_state: 'CO',
                ship_zip: '80401',
                ship_country: 'USA',
                ship_phone: '3036383171',
                ship_email: 'jeffsheffel@yahoo.com',
            };

            /*
            self.accommodated[0] = {
                battery_code: 'GM17ACM18AC',
                type: 'large_print',
                quantity: '2',
            };
            */
        }
    }
})();
