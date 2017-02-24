<?php

namespace App\Http\Controllers\Api\V1;

use App\PMetrics\Customer\Customer;
use App\PMetrics\District\District;
use App\PMetrics\School\School;

class CustomerServiceController extends ApiController
{
    protected $collectionName = 'customerservice';


    /**
     * Get data for the dashboard
     *
     * @return  array
     */
    public function downloadData()
    {
        $fileData = with(new Customer)->getAllDataExportExcel();

        if ($fileData !== false) {
            return $this->downloadXls($fileData['fullFileName'], $fileData['fileName']);
        } else {
            return false;
        }
    }

    /**
     * Global search
     *
     * @return  array
     */
    public function search()
    {
        $data = \Input::json()->all();
        $type = 'unknown';

        if (!isset($data['keyword'])) {
            return [];
        }

        $districts = District::where('id', '=', $data['keyword'])->orwhere('name', 'like', '%' . $data['keyword'] . '%')->with('schools')->get();
        $schools = School::where('id', '=', $data['keyword'])->orwhere('name', 'like', '%' . $data['keyword'] . '%')->with('district')->get();

        if ($districts->count() == 1 || ($districts->count() && !$schools->count())) {
            $type = 'District';
        } else if ($schools->count() == 1 || ($schools->count() && !$districts->count())) {
            $type = 'School';
        }

        return [
            'is_a' => $type,
            'district' => [
                'data' => $districts,
                'count' => $districts->count(),
            ],
            'school' => [
                'data' => $schools,
                'count' => $schools->count(),
            ]
        ];
    }

    /**
     * Get all info related to a school
     *
     * @return  array
     */
    public function getSchoolInfo()
    {
        $data = \Input::json()->all();
        $args = isset($data['summaryReportingPeriod']) ? [$data['schoolIds'], $data['summaryReportingPeriod']] : [$data['schoolIds']];

        return call_user_func_array( [new School, 'getInfo'], $args);
    }

    /**
     * Gets class info
     *
     * @param   int       $customerId
     * @param   int       $schoolId
     * @return  array
     */
    public function getClasses($customerId,  $schoolId)
    {
        $data = \Input::json()->all();
        $classes = with(new School)->getClasses($customerId,  $schoolId, $data['classIds']);

        return $classes;
    }

    /**
     * Gets students info
     *
     * @param   int       $customerId
     * @param   int       $schoolId
     * @return  array
     */
    public function getStudents($customerId,  $schoolId)
    {
        $data = \Input::json()->all();
        $students = with(new School)->getAccommodations($customerId,  $schoolId, $data['rosterIds']);

        return $students;
    }
}
