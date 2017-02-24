(function () {
    'use strict';

    angular
        .module('pmet')
        .factory('UtilityFactory', UtilityFactory);

    function UtilityFactory($http, API_ENDPOINT) {

        /**
         * Gets orders collection
         *
         * @returns {HttpPromise}
         */
        UtilityFactory.downloadCustomerData = function () {
            return $http.get(API_ENDPOINT + 'customerservice/download-data', {responseType: "arraybuffer"});
        };

        /**
         * Searches everything
         *
         * @param {string} keyword
         * @returns {HttpPromise}
         */
        UtilityFactory.search = function (keyword) {
            return $http.post(API_ENDPOINT + 'customerservice/search', {keyword: keyword});
        };

        /**
         * Retrieves full information about a school by id
         *
         * @param {string} schoolIds
         * @returns {HttpPromise}
         */
        UtilityFactory.getSchoolInfo = function (schoolIds) {
            return $http.post(API_ENDPOINT + 'customerservice/get-school-info', {schoolIds: schoolIds});
        };

        /**
         * Retrieves full information about a school by id and an optional Summary Reporting Period
         *
         * @param {array} schoolIds
         * @param {int}    reportingPeriod
         * @returns {HttpPromise}
         */
        UtilityFactory.getSchoolInfoWithSRP = function (schoolIds, summaryReportingPeriod) {
            return $http.post(API_ENDPOINT + 'customerservice/get-school-info', {schoolIds: schoolIds, summaryReportingPeriod: summaryReportingPeriod});
        };

        /**
         * Retrieves full information about classes
         *
         * @param {int} customerId
         * @param {int} schoolId
         * @param {array} classIds
         * @returns {HttpPromise}
         */
        UtilityFactory.getClasses = function (customerId, schoolId, classIds) {
            return $http.post(API_ENDPOINT + 'customerservice/get-classes/' + customerId + '/' + schoolId, {classIds: classIds});
        };

        /**
         * Retrieves full information about students
         *
         * @param {int} customerId
         * @param {int} schoolId
         * @param {array} rosterIds
         * @returns {HttpPromise}
         */
        UtilityFactory.getStudents = function (customerId, schoolId, rosterIds) {
            return $http.post(API_ENDPOINT + 'customerservice/get-students/' + customerId + '/' + schoolId, {rosterIds: rosterIds});
        };

        return UtilityFactory;
    }
})();
