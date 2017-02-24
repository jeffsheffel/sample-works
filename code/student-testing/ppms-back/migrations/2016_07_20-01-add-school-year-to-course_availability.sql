/*
 * US10947 - add school year attribute to course availability imports
 * by jsheffel
 *
 * Add school_year column to course_availabilty table and set column for existing rows.
 *
 * This script should be applied to all QC Admin database instances.
 * The associated update to the PPMS course availability import code (also part of US10947),
 * is dependent upon this database change.
 */

-- add school_year column to course_availability
alter table course_availability add column school_year int unsigned after sc_school_number;

-- add constraint to school_year.id
alter table course_availability
  add constraint fk_school_year_id
  foreign key (school_year) references school_year(id);

-- set default school_year, to SY2015-16, for all rows (including null order_numbers)
UPDATE course_availability SET school_year = (select id from school_year where display_name = 'SY2015-16');

-- set school_year, to SY2016-17, for order numbers at or above the ACT cutover order number (13208)
UPDATE course_availability SET school_year = (select id from school_year where display_name = 'SY2016-17')
  where order_number >= 13208;

