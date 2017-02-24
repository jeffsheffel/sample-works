<?php
/*
 * This unit test script validates the Student::processAccommodationChanges() method.
 * This script must be executed (via command line) in the script's containing directory (obviously?).
 *
 * The following describes the accommodation settings; partly taken from the CSV input user guide:
 *  http://www.qualitycore.act.org/wp-content/uploads/2015/08/District_CSV_Instructions_state_AL.pdf
 *
 * Education Classification:    tinyint(1)
 *  0|00    = regular ed
 *  1|01    = special ed
 *  2|02    = gifted or talented
 * LEP Status:                  char(2)
 *  1|01    = fully English proficient
 *  2|02    = limited English proficiency
 * Section 504 Status:          tinyint(1)
 *  N       - 0 in database; must be N for special ed
 *  Y       - 1 in database
 *
 * NOTES:
 * ) Section 504 Status: CSV input as "N|Y" but stored in database as 0|1
 * ) refer to Framework/constants.php
 */

require_once '../../Framework/class.Student.php';
require_once '../../Framework/constants.php';

/*
try {
    // originally was to signify no changes to accommodations, but empty settings are invalidated by district CSV checks
    print("\n\nTEST: New accommodations are all empty\n");
    $studentData['st_education_classification'] = 1;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 1;
    $newAccommodationsData['st_education_classification'] = "";
    $newAccommodationsData['st_LEP_status'] = "";
    $newAccommodationsData['st_504_status'] = "";
    $studentData['st_au_id'] = null;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}
*/

try {
    print("\n\nTEST: Null accommodation element in \$studentData\n");
    $studentData['st_education_classification'] = null;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 1;
    $newAccommodationsData['st_education_classification'] = "";
    $newAccommodationsData['st_LEP_status'] = "01";
    $newAccommodationsData['st_504_status'] = 1;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: Null accommodation element in \$newAccommodationsData\n");
    $studentData['st_education_classification'] = 1;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 1;
    $newAccommodationsData['st_education_classification'] = "";
    $newAccommodationsData['st_LEP_status'] = null;
    $newAccommodationsData['st_504_status'] = 1;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: Changed clear LEP status (from 02 to 01) but student is still Special Ed\n");
    $studentData['st_education_classification'] = 1;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 0;
    $newAccommodationsData['st_education_classification'] = 1;
    $newAccommodationsData['st_LEP_status'] = "01";  // changed LEP status
    $newAccommodationsData['st_504_status'] = 0;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: Setting accommodated student as non-accommodated\n");
    $studentData['st_education_classification'] = 0;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 0;
    $newAccommodationsData['st_education_classification'] = 0;
    $newAccommodationsData['st_LEP_status'] = "01";  // changed LEP status
    $newAccommodationsData['st_504_status'] = 0;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: No changes to a non-accommodated student\n");
    $studentData['st_education_classification'] = 0;
    $studentData['st_LEP_status'] = "01";
    $studentData['st_504_status'] = 0;
    $newAccommodationsData['st_education_classification'] = 0;
    $newAccommodationsData['st_LEP_status'] = "01";
    $newAccommodationsData['st_504_status'] = 0;

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: Changed Gifted to Special Ed with \$flagDeleteSessionsAndAccommodations = true, BUT NULL student ID (st_au_id) in student array\n");
    $studentData['st_education_classification'] = 2;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 1;
    $newAccommodationsData['st_education_classification'] = 1;  // change to Special Ed student
    $newAccommodationsData['st_LEP_status'] = "02";
    $newAccommodationsData['st_504_status'] = 1;
    $studentData['st_au_id'] = null;                // st_au_id set to NULL, should cause exception

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData, true);

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}

try {
    print("\n\nTEST: Unset 504 status with \$flagDeleteSessionsAndAccommodations = true, BUT no \$dbc\n");
    $studentData['st_education_classification'] = 2;
    $studentData['st_LEP_status'] = "02";
    $studentData['st_504_status'] = 1;
    $newAccommodationsData['st_education_classification'] = 2;
    $newAccommodationsData['st_LEP_status'] = "02";
    $newAccommodationsData['st_504_status'] = 0;    // unset 504 status
    $studentData['st_au_id'] = 123456789;           // st_au_id set to some bogus value, expecting $dbc null exception

    $result = Student::processAccommodationChanges($studentData, $newAccommodationsData, true); // $dbc not passed here!

    var_dump($result);
} catch (\Exception $e) {
    echo 'ERROR: caught exception: ',  $e->getMessage(), "\n";
}
