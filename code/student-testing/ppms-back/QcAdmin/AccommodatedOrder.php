<?php namespace App\PMetrics\QcAdmin;

use App\PMetrics\QcAdmin\QcAdmin;
use App\PMetrics\Customer\Customer;
use App\PMetrics\School\School;


class AccommodatedOrder extends QcAdmin {

	/**
     * Formats district/school/student/accommodations for accommodated orders
     *
     * @param   int     $customerId
     * @param   array   $data
     * @param  	array 	$rosterIds
     * @return  array
     */
    public function packageAdminAccommodations($customerId, $data = null, $rosterIds = [])
    {
		$orders = [];
        $return = [];
        $data = $data ?: (! sizeof($rosterIds) ? $this->getAdminAccommodations($customerId) : $this->getAdminAccommodationsByStudents($customerId, $rosterIds));

        foreach ($data as $val) {
		    $orders[$val["school_id"]][] = $val;
		}

		foreach ($orders as $schoolId=>$school) {

		    $students = [];

		    foreach ($school as $key=>$val) {

				// save the info for the school, but if there is a district shipping address, use that
		    	if ($key === 0 || ($val['ship_type'] == 'district' && $order['ship_type'] == 'school')) {

		    		$contactPrepend = $val['ship_full_name'] != '' ? 'ship' : 'district';
		    		$addressPrepend = $val['ship_address'] != '' ? 'ship' : 'district';
		    		$shipTo = School::getShipTo($customerId, $val['school'], $val['district']);

                    $order = [
						'expedited' => 0,
						'status' => 'created',  // this is ignored
						'testing_start_date' => $val['testing_start_date'],
						'testing_end_date' => $val['testing_end_date'],
						'ship_level' => 'Ground',
						'contract_code' => '123',
						'school_id' => $val['school_id'],
						'school_name' => $val['school'],
						'district_id' => $val['district_id'],
						'district_name' => $val['district'],
						'ship_to' => $shipTo,
						'ship_first_name' => $val[$contactPrepend . '_first_name'],
						'ship_last_name' => $val[$contactPrepend . '_last_name'],
						'ship_address' => $val[$addressPrepend . '_address'],
						'ship_address_2' => $val[$addressPrepend . '_address2'],
						'ship_address_3' => '',
						'ship_city' => $val[$addressPrepend . '_city'],
						'ship_state' => $val[$addressPrepend . '_state'],
						'ship_zip' => $val[$addressPrepend . '_zip'],
						'ship_country' => $val[$addressPrepend . '_country'],
						'ship_phone' => $val[$contactPrepend . '_phone'],
						'ship_email' => $val[$contactPrepend . '_email'],

						'ship_type' => $val['ship_type'],
						'deliver_to' => $val['deliver_to'],
						'deliver_to_email' => $val['deliver_email'],
						'deliver_to_phone' => $val['deliver_phone'],

						'contact_first_name' => $val[$contactPrepend . '_first_name'],
						'contact_last_name' => $val[$contactPrepend . '_last_name'],
				    ];
		    	}

			    $students[] = [
					'qc_id' => $val['qc_id'] ?: strtoupper($customerId) . str_pad($val['student_id'], 8, '0', STR_PAD_LEFT),
					'first_name' => $val['student_first_name'],
					'last_name' => $val['student_last_name'],
					'grade' => $val['grade'],
					'teacher_name' => $val['teacher_first_name'] . ' ' . $val['teacher_last_name'],
					'course' => $val['course_code'],
					'district_name' => $val['district'],
					'school_name' => $val['school'],
					'state' => $customerId,
					'class_id' => $val['course_id'],            // NOTE: transforms courses.course_id to class_id, which renders as <ClassId> in XML order, useless?
					'class_name' => $val['course_description'],
					'test_id' => $val['test_id'],
					'roster_id' => $val['teacher_class_id'],    // NOTE: transforms teacher_class.tc_id to roster_id, which renders as <RosterId> in XML order
					'roster_name' => $val['roster'],
					'large_print' => $val['large_print'],
					'braile' => $val['braille'],
					'audio_cd' => $val['audio_cd'],
					'reader_script' => (int) ($val['reader_script'] || $val['reader_script_lep']),
					'battery_code' => $val['battery'],
					'quantity' => 1,
			    ];
			}

			// remove duplicate entries (occurs if there are two shipping addresses for the same record)
			$students = array_unique($students, SORT_REGULAR);

            $return[] = ['order' => $order, 'students' => $students];
		}

		return $return;
    }

    /**
     * Queries for all accommodated material orders, for all classes with a STW that begins in exactly 30-days, for a single QC Admin instance.
     *
     * Refer to similar query in QcAdmin/PrintOrder.php module;
     * though one significant difference is how the contact and address data is joined.
     *
     * @param   int       $customerId
     * @return  array
     * @todo    whatever updates we do to one query, also do to this one
     */
    public function getAdminAccommodations($customerId)
    {
		$defaultShipPhone = \Config::get('pmet.defaultShipPhone');

    	return $this->getPdo($customerId)->query("
            SELECT '{$customerId}' as customer_id,
                sc.sc_external_id AS school_id,
                sc.sc_name AS school,
                sc.sc_district_number AS district_id,
                sc.sc_district AS district,
                t.t_au_id AS teacher_id,
                t.t_first_name AS teacher_first_name,
                t.t_last_name AS teacher_last_name,
                s.st_au_id AS student_id,
                s.st_first_name AS student_first_name,
                s.st_last_name AS student_last_name,
                s.st_grade AS grade,
                s.qc_id,
                r.r_id AS roster_id,
                r.r_tc_id AS teacher_class_id,
                CONCAT(t.t_first_name, ' ', t.t_last_name, '''s ', c.course_description, ' Section ', tc.tc_section) AS roster,
                a.ac_504_large_print AS large_print,
                a.ac_504_braille AS braille,
                a.ac_504_audio_cd AS audio_cd,
                a.ac_504_test_read_aloud AS reader_script,
                a.ac_lep_test_read_aloud AS reader_script_lep,
                c.course_id AS course_id,
                c.course_code,
                c.course_description,
                c.course_type,
                tfx.battery_code AS battery,
                tfx.form_number AS test_id,
                stw.start_date,
                DATE_FORMAT(stw.start_date, '%m/%d/%Y') AS testing_start_date,
                DATE_FORMAT(stw.end_date, '%m/%d/%Y') AS testing_end_date,
                DATE_ADD(CAST(NOW() AS DATE), INTERVAL 30 DAY) AS 30_days_from_now,

                aa.address AS ship_address,
                aa.address2 AS ship_address2,
                aa.city AS ship_city,
                aa.state AS ship_state,
                aa.zip AS ship_zip,
                aa.country AS ship_country,

                ac.full_name AS ship_full_name,
                ac.firstname AS ship_first_name,
                ac.lastname AS ship_last_name,
                IFNULL(NULLIF(CONCAT(ac.areacode, ac.phone, IFNULL(CONCAT_WS('x', ac.extension), '')), ''),
                  '{$defaultShipPhone}') AS ship_phone,
                ac.email AS ship_email,

                aa_district.address AS district_address,
                aa_district.address2 AS district_address2,
                aa_district.city AS district_city,
                aa_district.state AS district_state,
                aa_district.zip AS district_zip,
                aa_district.country AS district_country,
                ac_district.full_name AS district_full_name,
                ac_district.firstname AS district_first_name,
                ac_district.lastname AS district_last_name,
                IFNULL(NULLIF(CONCAT(ac_district.areacode, ac_district.phone, IFNULL(CONCAT_WS('x', ac_district.extension), '')), ''),
                  '{$defaultShipPhone}') AS district_phone,
                ac_district.email AS district_email,

                aa_type.object AS ship_type,
                ac_d.full_name AS deliver_to,
                ac_d.email AS deliver_email,
                IFNULL(CONCAT(ac_d.areacode, ac_d.phone, IFNULL(CONCAT_WS('x', ac_d.extension), '')), '') AS deliver_phone

            FROM
                teacher_class tc
                    INNER JOIN
                roster r ON tc.tc_id = r.r_tc_id
                    INNER JOIN
                teacher t ON tc.tc_au_id = t.t_au_id
                    INNER JOIN
                student s ON r.r_st_au_id = s.st_au_id
                    INNER JOIN
                school sc ON s.st_school_number = sc.sc_school_number
                    INNER JOIN
                courses c ON tc.tc_course_code = c.course_code
                    AND c.course_delivery = 'PNP'
                    -- AND c.course_active = 1      -- should not need this condition, class cannot be assigned an inactive course
                    INNER JOIN
                student_test_record str ON r.r_course_type = str.r_course_type
                  AND s.st_au_id = str.st_au_id
                    INNER JOIN
                test_forms tf ON str.tf_id = tf.tf_id
                    INNER JOIN
                accommodations a ON s.st_au_id = a.ac_st_au_id
                    LEFT JOIN
                test_forms_xref tfx ON SUBSTR(tf.tf_external_id, 1, 5) = SUBSTR(tfx.battery_code, 1, 5)
                  AND SUBSTR(tf.tf_external_id, 7, 4) = SUBSTR(tfx.battery_code, 7, 4)
                  AND tfx.delivery = 'PNP'
                    INNER JOIN
                school_testing_windows stw ON tc.school_testing_window_id = stw.id
                  AND stw.tw_id = c.tw_id                   -- required, otherwise multiple course_id's returned
                  AND tc.school_testing_window_id = stw.id
                  AND stw.start_date = DATE_ADD(CAST(NOW() AS DATE), INTERVAL 30 DAY)

                    LEFT JOIN
                act_addresses aa ON sc.sc_external_id = aa.fk_id
                  AND aa.object = 'school'
                  AND aa.type = 'ship'
                  AND aa.created_at IN (SELECT MAX(aa2.created_at) FROM act_addresses aa2
                      WHERE sc.sc_external_id = aa2.fk_id AND aa2.object = 'school' AND aa2.type = 'ship')
                    LEFT JOIN
                act_contacts ac ON sc.sc_external_id = ac.fk_id
                  AND ac.object = 'school'
                  AND ac.type = 'ship'
                  AND ac.created_at IN (SELECT MAX(ac2.created_at) FROM act_contacts ac2
                      WHERE sc.sc_external_id = ac2.fk_id AND ac2.object = 'school' AND ac2.type = 'ship')
                    LEFT JOIN
                act_addresses aa_type ON aa_type.type = 'ship'
                  AND (sc.sc_external_id = aa_type.fk_id
                  OR sc.sc_district_number = aa_type.fk_id)
                  AND aa_type.created_at IN (SELECT MAX(aa_type2.created_at) FROM act_addresses aa_type2
                      WHERE aa_type2.type = 'ship' AND (sc.sc_external_id = aa_type2.fk_id OR sc.sc_district_number = aa_type2.fk_id))
                    LEFT JOIN
                act_contacts ac_d ON ac_d.type = 'deliver'
                  AND CASE
                    WHEN
                        aa_type.object = 'school'
                    THEN
                        sc.sc_external_id = ac_d.fk_id AND ac_d.created_at IN
                          (SELECT MAX(ac_d2.created_at) FROM act_contacts ac_d2
                            WHERE sc.sc_external_id = ac_d2.fk_id AND ac_d2.type = 'deliver')
                    ELSE sc.sc_district_number = ac_d.fk_id AND ac_d.created_at IN
                        (SELECT MAX(ac_d3.created_at) FROM act_contacts ac_d3
                          WHERE sc.sc_district_number = ac_d3.fk_id AND ac_d3.type = 'deliver')
                    END
                    LEFT JOIN
                act_addresses aa_district ON sc.sc_district_number = aa_district.fk_id
                  AND aa_district.object = 'district'
                  AND aa_district.type = 'ship'
                  AND aa_district.created_at IN
                      (SELECT MAX(aa_district2.created_at) FROM act_addresses aa_district2
                        WHERE sc.sc_external_id = aa_district2.fk_id AND aa_district2.object = 'district' AND aa_district2.type = 'ship')
                    LEFT JOIN
                act_contacts ac_district ON sc.sc_district_number = ac_district.fk_id
                  AND ac_district.object = 'district'
                  AND ac_district.type = 'ship'
                  AND ac_district.created_at IN
                      (SELECT MAX(ac_district2.created_at) FROM act_contacts ac_district2
                        WHERE sc.sc_external_id = ac_district2.fk_id AND ac_district2.object = 'district' AND ac_district2.type = 'ship')

            WHERE
                (a.ac_504_large_print = 1
                    OR a.ac_504_braille = 1
                    OR a.ac_504_audio_cd = 1
                    OR a.ac_504_test_read_aloud = 1
                    OR a.ac_lep_test_read_aloud = 1)
            ORDER BY sc.sc_school_number, r.r_tc_id, s.st_last_name, s.st_first_name
        ", \PDO::FETCH_ASSOC);
	}

    /**
     * Queries for all accommodated material orders, for (manual UI) requested rosters, for a single QC Admin instance.
     *
     * This method basically differs from the getAdminAccommodations() method, in that, roster IDs are passed in for filtering, and
     * the 30-day query clause is not used.
     *
     * @param  int   $customerId
     * @param  array $rosterIds
     * @return array
     */
    public function getAdminAccommodationsByStudents($customerId, $rosterIds)
    {
		$defaultShipPhone = \Config::get('pmet.defaultShipPhone');
        $rosters = "and r.r_id in (" . implode(',', $rosterIds) . ")";

        return $this->getPdo($customerId)->query("
            SELECT '{$customerId}' as customer_id,
                sc.sc_external_id AS school_id,
                sc.sc_name AS school,
                sc.sc_district_number AS district_id,
                sc.sc_district AS district,
                t.t_au_id AS teacher_id,
                t.t_first_name AS teacher_first_name,
                t.t_last_name AS teacher_last_name,
                s.st_au_id AS student_id,
                s.st_first_name AS student_first_name,
                s.st_last_name AS student_last_name,
                s.st_grade AS grade,
                s.qc_id,
                r.r_id AS roster_id,
                r.r_tc_id AS teacher_class_id,
                CONCAT(t.t_first_name, ' ', t.t_last_name, '''s ', c.course_description, ' Section ', tc.tc_section) AS roster,
                a.ac_504_large_print AS large_print,
                a.ac_504_braille AS braille,
                a.ac_504_audio_cd AS audio_cd,
                a.ac_504_test_read_aloud AS reader_script,
                a.ac_lep_test_read_aloud AS reader_script_lep,
                c.course_id AS course_id,
                c.course_code,
                c.course_description,
                c.course_type,
                tfx.battery_code AS battery,
                tfx.form_number AS test_id,
                stw.start_date,
                DATE_FORMAT(stw.start_date, '%m/%d/%Y') AS testing_start_date,
                DATE_FORMAT(stw.end_date, '%m/%d/%Y') AS testing_end_date,
                DATE_ADD(CAST(NOW() AS DATE), INTERVAL 30 DAY) AS 30_days_from_now,

                aa.address AS ship_address,
                aa.address2 AS ship_address2,
                aa.city AS ship_city,
                aa.state AS ship_state,
                aa.zip AS ship_zip,
                aa.country AS ship_country,

                ac.full_name AS ship_full_name,
                ac.firstname AS ship_first_name,
                ac.lastname AS ship_last_name,
                IFNULL(NULLIF(CONCAT(ac.areacode, ac.phone, IFNULL(CONCAT_WS('x', ac.extension), '')), ''),
                  '{$defaultShipPhone}') AS ship_phone,
                ac.email AS ship_email,

                aa_district.address AS district_address,
                aa_district.address2 AS district_address2,
                aa_district.city AS district_city,
                aa_district.state AS district_state,
                aa_district.zip AS district_zip,
                aa_district.country AS district_country,
                ac_district.full_name AS district_full_name,
                ac_district.firstname AS district_first_name,
                ac_district.lastname AS district_last_name,
                IFNULL(NULLIF(CONCAT(ac_district.areacode, ac_district.phone, IFNULL(CONCAT_WS('x', ac_district.extension), '')), ''),
                  '{$defaultShipPhone}') AS district_phone,
                ac_district.email AS district_email,

                aa_type.object AS ship_type,
                ac_d.full_name AS deliver_to,
                ac_d.email AS deliver_email,
                IFNULL(CONCAT(ac_d.areacode, ac_d.phone, IFNULL(CONCAT_WS('x', ac_d.extension), '')), '') AS deliver_phone

            FROM
                teacher_class tc
                    INNER JOIN
                roster r ON tc.tc_id = r.r_tc_id
                    INNER JOIN
                teacher t ON tc.tc_au_id = t.t_au_id
                    INNER JOIN
                student s ON r.r_st_au_id = s.st_au_id
                    INNER JOIN
                school sc ON s.st_school_number = sc.sc_school_number
                    INNER JOIN
                courses c ON tc.tc_course_code = c.course_code
                    AND c.course_delivery = 'PNP'
                    -- AND c.course_active = 1      -- should not need this condition, class cannot be assigned an inactive course
                    INNER JOIN
                student_test_record str ON r.r_course_type = str.r_course_type
                  AND s.st_au_id = str.st_au_id
                    INNER JOIN
                test_forms tf ON str.tf_id = tf.tf_id
                    INNER JOIN
                accommodations a ON s.st_au_id = a.ac_st_au_id
                    LEFT JOIN
                test_forms_xref tfx ON SUBSTR(tf.tf_external_id, 1, 5) = SUBSTR(tfx.battery_code, 1, 5)
                  AND SUBSTR(tf.tf_external_id, 7, 4) = SUBSTR(tfx.battery_code, 7, 4)
                  AND tfx.delivery = 'PNP'
                    INNER JOIN
                school_testing_windows stw ON tc.school_testing_window_id = stw.id
                  AND stw.tw_id = c.tw_id                   -- required, otherwise multiple course_id's returned
                  AND tc.school_testing_window_id = stw.id

                    LEFT JOIN
                act_addresses aa ON sc.sc_external_id = aa.fk_id
                  AND aa.object = 'school'
                  AND aa.type = 'ship'
                  AND aa.created_at IN (SELECT MAX(aa2.created_at) FROM act_addresses aa2
                      WHERE sc.sc_external_id = aa2.fk_id AND aa2.object = 'school' AND aa2.type = 'ship')
                    LEFT JOIN
                act_contacts ac ON sc.sc_external_id = ac.fk_id
                  AND ac.object = 'school'
                  AND ac.type = 'ship'
                  AND ac.created_at IN (SELECT MAX(ac2.created_at) FROM act_contacts ac2
                      WHERE sc.sc_external_id = ac2.fk_id AND ac2.object = 'school' AND ac2.type = 'ship')
                    LEFT JOIN
                act_addresses aa_type ON aa_type.type = 'ship'
                  AND (sc.sc_external_id = aa_type.fk_id
                  OR sc.sc_district_number = aa_type.fk_id)
                  AND aa_type.created_at IN (SELECT MAX(aa_type2.created_at) FROM act_addresses aa_type2
                      WHERE aa_type2.type = 'ship' AND (sc.sc_external_id = aa_type2.fk_id OR sc.sc_district_number = aa_type2.fk_id))
                    LEFT JOIN
                act_contacts ac_d ON ac_d.type = 'deliver'
                  AND CASE
                    WHEN
                        aa_type.object = 'school'
                    THEN
                        sc.sc_external_id = ac_d.fk_id AND ac_d.created_at IN
                          (SELECT MAX(ac_d2.created_at) FROM act_contacts ac_d2
                            WHERE sc.sc_external_id = ac_d2.fk_id AND ac_d2.type = 'deliver')
                    ELSE sc.sc_district_number = ac_d.fk_id AND ac_d.created_at IN
                        (SELECT MAX(ac_d3.created_at) FROM act_contacts ac_d3
                          WHERE sc.sc_district_number = ac_d3.fk_id AND ac_d3.type = 'deliver')
                    END
                    LEFT JOIN
                act_addresses aa_district ON sc.sc_district_number = aa_district.fk_id
                  AND aa_district.object = 'district'
                  AND aa_district.type = 'ship'
                  AND aa_district.created_at IN
                      (SELECT MAX(aa_district2.created_at) FROM act_addresses aa_district2
                        WHERE sc.sc_external_id = aa_district2.fk_id AND aa_district2.object = 'district' AND aa_district2.type = 'ship')
                    LEFT JOIN
                act_contacts ac_district ON sc.sc_district_number = ac_district.fk_id
                  AND ac_district.object = 'district'
                  AND ac_district.type = 'ship'
                  AND ac_district.created_at IN
                      (SELECT MAX(ac_district2.created_at) FROM act_contacts ac_district2
                        WHERE sc.sc_external_id = ac_district2.fk_id AND ac_district2.object = 'district' AND ac_district2.type = 'ship')

            WHERE
                (a.ac_504_large_print = 1
                    OR a.ac_504_braille = 1
                    OR a.ac_504_audio_cd = 1
                    OR a.ac_504_test_read_aloud = 1
                    OR a.ac_lep_test_read_aloud = 1)
			{$rosters}
            ORDER BY sc.sc_school_number, r.r_tc_id, s.st_last_name, s.st_first_name
        ", \PDO::FETCH_ASSOC);
    }
}
