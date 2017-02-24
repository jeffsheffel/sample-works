# Code Samples

The code in this subdirectory is a sample of code from _medium-scale_ enterprise applications.
As such, there is a large number of source code files, which were selected to highlight the
salient components and functionality of the overall application. By scanning the directory structures, one can gain insight into the application structure.

The code samples are further organized into subdirectories that are named according to the company name, that matches my resume experience.

The fiber-to-home/ code (from 2013) consisted of a major refactor of an enterprise application.
The PHP code ran under an older version of PHP (5.2). The code demonstrates interfacing with a diverse collection of laboratory and production hardware, through SOAP and SNMP.

The student-testing/ code is from 2016.
In my six months with the company, I contributed to several enterprise applications, within a suite of applications, that
managed student tests (... think ACT or SAT testing).
Most of the work involved modifying the existing frameworks; custom PHP framework, Laravel, and Angular.
As such, much of the sample works are *not* 100-percent my programming effort, but rather debugs and refactors.

The pdf/ directory contains PHP code to dynamically generate PDF files from user filled forms.

The code directory structure is as follows:

fiber-to-home/
-------------------------------------------------------
- vader/ - top-level (Apache) web application directory
    - calix/ - a specific hardware vendor subdirectory
    - include/
    - lib/
    - test/
    * web/

student-testing/
-------------------------------------------------------
- dev/
    - dev/php/psr/php_cs.php
- ppms-back/
    - app/Http/routes.php
    - app/Http/Controllers/Api/V1/CustomerServiceController.php
    - migrations/2016_07_20-01-add-school-year-to-course_availability.sql
    - QcAdmin/AccommodatedOrder.php - **a good example of a SQL refactor for bug fix and optimization!**
    - unit-test/test-class-Student.php
- ppms-front/
    - app/components/combinedorder/CombinedCreateController.js
    - app/components/combinedorder/views/create.html
    - app/components/utilities/UtilityFactory.js

pdf/
-------------------------------------------------------
- pdf-service/
    - diagram-data-flow-pdfgen.jpg
    - Makefile
    - PdfService/DaemonAction.php
    - PdfService/pdfgen-programming-notes.txt
        - Object/Pdf.php
        - Object/PdfDealLot.php
        - Object/PdfW9Page.php
        - Object/PdfSignW9.php

