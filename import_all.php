<?php

gc_disable();



include "simple_html_dom.php";

$iso15924_fn = '';

$nn = get_native_names();
// printf("Count2 [%s]\n", count($nn));

get_iso_codes(); // does a git update

get_geonames();
get_unicode();
$sil_date = get_sil();
get_ethnologue();



$iso15924_fn = get_iso15924();

import_files($iso15924_fn, $sil_date, $nn);

printf("%s\n", str_repeat("-", 80));

printf("All done.\n");

return 0;




function	import_files($iso15924_fn = "", $sil_date = "", $nn) {
// ----------------------------------------------------------------------------
printf("%s\n", str_repeat("-", 80));
$link = mysqli_connect("localhost", "root", "Trees1Trees!", "language_data");

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

mysqli_set_charset($link, 'utf8');

$rc = import_file($link, "/tmp/unicode_languageData.tab", 'unicode_languageData');
$rc = import_file($link, "/tmp/unicode_territoryInfo.tab", 'unicode_territoryInfo');

$rc = import_file($link, "/tmp/geonames.tab", 'geonames');

// Ethnologue
$rc = import_file($link, "/tmp/CountryCodes.tab", 'CountryCodes');
$rc = import_file($link, "/tmp/LanguageCodes.tab", 'LanguageCodes');
$rc = import_file($link, "/tmp/LanguageIndex.tab", 'LanguageIndex');

// SIL
$rc = import_file($link, "/tmp/iso-639-3_" . $sil_date . ".tab", 'ISO_639-3');
$rc = import_file($link, "/tmp/iso-639-3_Name_Index_" . $sil_date . ".tab", 'ISO_639-3_Names');
$rc = import_file($link, "/tmp/iso-639-3_Retirements_" . $sil_date . ".tab", 'ISO_639-3_Retirements');
// $rc = import_file($link, "/tmp/iso-639-3-macrolanguages_" . $sil_date . ".tab", 'ISO_639-3_Macrolanguages');

if (!empty($iso15924_fn)) {
  $rc = import_file($link, $iso15924_fn, 'iso15924', ";", "\n", "code,number,english_name,french_name,PVA,date", 7);
}

$rc = import_iso_codes($link);

if (!empty($nn)) {
	write_native_names($link, 'native_names', $nn);
}


mysqli_close($link);

return 0;
}


// ===================================================================

function	import_file($db, $filename, $table_name, $separator = "\t", $line_term = "\r\n", $col_names = "", $ignore_lines = 1) {
	printf("Importing [%s] into [%s]...\n", $filename, $table_name);

	if(!file_exists($filename)) {
		printf("  --> Could not find [%s]\n", $filename);
		return FALSE;
	}

	$handle = fopen($filename, "r");
	if ($handle) {
		$bom = fread($handle, 3);
		if ($bom != b"\xEF\xBB\xBF") {
			rewind($handle);
		}

		if (empty($col_names) && ($buffer = fgets($handle, 4096)) !== false) {
			// header line
			$header = explode($separator, $buffer);
			// print_r($header);
			$col_names = trim(implode(",", $header));
		}

		// START TRANSACTION;
		if (FALSE === mysqli_query($db, "START TRANSACTION;")) {
			printf("START failed\n");
			printf("Error: %s\n", mysqli_error($db));
			return FALSE;
		}

		if (FALSE === mysqli_query($db, sprintf("TRUNCATE `%s`;", $table_name))) {
			printf("TRUNCATE failed\n");
			printf("Error: %s\n", mysqli_error($db));
			return FALSE;
		}


/* TOO SLOW
		while (($buffer = fgets($handle, 4096)) !== false) {
			// data lines
			$line = explode($separator, $buffer);
			$line_data = '"' . trim(implode('","', $line)) . '"';
			$sql = sprintf("INSERT INTO %s (%s) VALUE (%s);", $table_name, $col_names, $line_data);
			// print_r($sql . "\n");
			if (FALSE === mysqli_query($db, $sql)) {
				printf("Insert failed.\n");
				return FALSE;
			}


		}
*/
			// http://stackoverflow.com/questions/4215231/mysql-load-data-infile-error-code-13
			$sql = sprintf("LOAD DATA INFILE '%s' INTO TABLE `%s` FIELDS TERMINATED BY '%s' LINES TERMINATED BY '%s' IGNORE %d LINES (%s);", $filename, $table_name, $separator, $line_term, $ignore_lines, $col_names);
			if (FALSE === mysqli_query($db, $sql)) {
				printf("Error: %s\n", mysqli_error($db));
			}
			
		// COMMIT;
		if (FALSE === mysqli_query($db, "COMMIT;")) {
			printf("COMMIT failed\n");
			return FALSE;
		}
/*
		if (!feof($handle)) {
			echo "Error: unexpected fgets() fail\n";
		}
*/
		fclose($handle);
		unlink($filename);
	}
}

function  cleanup($string) {
  return (trim(html_entity_decode(strip_tags(trim($string))), ":"));
}


function  get_iso15924() {
	// ----------------------------------------------------------------------------
  // http://www.unicode.org/iso15924/iso15924.txt.zip
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading ISO 15924 file from unicode.org... ");
	$filename = "iso15924.txt.zip";
	$bytes = file_put_contents("/tmp/" . $filename, fopen("http://www.unicode.org/iso15924/iso15924.txt.zip", 'r'));
	printf("Done\n");
	// printf("Bytes [%s]\n", $bytes);
	$out_fn = "";
	if ($bytes > 0) {
		$cmd = sprintf("unzip -o -d /tmp /tmp/%s iso15924-utf8-*.txt", $filename);
		exec($cmd, $output, $rc);
		unlink("/tmp/" . $filename);
		// printf("RC [%s]  Output [%s]\n", $rc, print_r($output, TRUE));

    $parts = explode(": ", $output[1]);
    $out_fn = trim($parts[1]);
	}
	
	// printf("Out fn [%s]\n", $out_fn);
	return $out_fn;
}


function	get_unicode() {
	// ----------------------------------------------------------------------------
	// http://unicode.org/repos/cldr/trunk/common/supplemental/supplementalData.xml
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading file from unicode.org... ");
	$html = file_get_html('http://unicode.org/repos/cldr/trunk/common/supplemental/supplementalData.xml');
	// $html = file_get_contents('http://unicode.org/repos/cldr/trunk/common/supplemental/supplementalData.xml');
	printf("Done\n");

  get_unicode_languageData($html);
  get_unicode_territoryInfo($html);
}

function  get_unicode_languageData($html) {
	printf("Processing language data... ");
	// initialize empty array to store the data array from each row
	$theData = array();

	// loop over rows
	foreach($html->find('supplementalData languageData language') as $row) {
		$type = $row->type;
		(!empty($row->type)) ? $theData[$type]['type'] = $row->type : NULL;
		(!empty($row->scripts)) ? $theData[$type]['scripts'] = $row->scripts : NULL;
		(!empty($row->territories)) ? $theData[$type]['territories'] = $row->territories : NULL;
		(!empty($row->alt)) ? $theData[$type]['alt'] = $row->alt : NULL;
	}
	printf("Done\n");

	// printf("Found [%d]\n", count($theData));

	$line[0] = 'type';
	$line[1] = 'scripts';
	$line[2] = 'territories';
	$line[3] = 'alt';

	$lines = sprintf("%s\r\n", implode("\t", $line));;

	foreach($theData as $key => $data) {
		$line[0] = isset($data['type']) ? $data['type'] : "";
		$line[1] = isset($data['scripts']) ? $data['scripts'] : "";
		$line[2] = isset($data['territories']) ? $data['territories'] : "";
		$line[3] = isset($data['alt']) ? $data['alt'] : "";
		$lines .= sprintf("%s\r\n", implode("\t", $line));;
	}

	printf("Writing... ");
	file_put_contents("/tmp/unicode_languageData.tab", $lines);
	printf("Done\n");
}


function  get_unicode_territoryInfo($html) {
	printf("Processing territory info... ");
	// initialize empty array to store the data array from each row
	$theData = array();

	// loop over rows
	foreach($html->find('supplementalData territoryInfo territory') as $row) {
		$type = $row->type;
		(!empty($row->type)) ? $theData[$type]['type'] = $row->type : NULL;
		(!empty($row->gdp ))? $theData[$type]['gdp'] = $row->gdp : NULL;
		(!empty($row->literacypercent)) ? $theData[$type]['literacypercent'] = $row->literacypercent : NULL;
		(!empty($row->population)) ? $theData[$type]['population'] = $row->population : NULL;
	}
	printf("Done\n");

	// printf("Found [%d]\n", count($theData));

	$line[0] = 'type';
	$line[1] = 'gdp';
	$line[2] = 'literacypercent';
	$line[3] = 'population';

	$lines = sprintf("%s\r\n", implode("\t", $line));;

	foreach($theData as $key => $data) {
		$line[0] = isset($data['type']) ? $data['type'] : "";
		$line[1] = isset($data['gdp']) ? $data['gdp'] : "";
		$line[2] = isset($data['literacypercent']) ? $data['literacypercent'] : "";
		$line[3] = isset($data['population']) ? $data['population'] : "";
		$lines .= sprintf("%s\r\n", implode("\t", $line));;
	}

	printf("Writing... ");
	file_put_contents("/tmp/unicode_territoryInfo.tab", $lines);
	printf("Done\n");
}


function	get_geonames() {
	// ----------------------------------------------------------------------------
	// http://www.geonames.de/languages.html
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading file from geonames... ");
	$html = file_get_html('http://www.geonames.de/languages.html');
	// $html = file_get_contents('http://www.geonames.de/languages.html');
	printf("Done\n");

	printf("Processing ... ");
	// initialize empty array to store the data array from each row
	$theData = array();

	// loop over rows

//	$table = $html->find('table tbody',1);
//	foreach($table->find('tr') as $row) {
	// $table = $html->find('table tbody',1);
	foreach($html->find('table[class=unicode] tbody tr') as $row) {
	  // print_r($row); die();

		// initialize array to store the cell data from each row
		$rowData = array();
		foreach($row->find('td') as $cell) {
  	  // print_r($cell->innertext); die();

			// push the cell's text to the array
			//    $string = mb_convert_encoding(cleanup($cell->innertext), 'HTML-ENTITIES', 'UTF-8');
			$string = cleanup($cell->innertext);
  	  // print_r($string); die();
			$rowData[] = $string;
		}

		// push the row's data array to the 'big' array
    $theData[] = $rowData;
	}

	// print_r($theData[0]); return;

  // Rewrite the first element to sane field names
	$theData[0][0] = 'code';
	$theData[0][1] = 'english_name';
	$theData[0][2] = 'native_long_form';
	$theData[0][3] = 'native_short_form';

	$lines = "";
	foreach ($theData as $key => $line) {
		if ($key > 0 && $line[0] == 'code') {
			// continue;
		}
		$lines .= sprintf("%s\r\n", implode("\t", $line));
	}

	printf("writing... ");
	file_put_contents("/tmp/geonames.tab", $lines);
	printf("Done\n");

	// print_r($theData);
	// printf("ML [%d]\n", $max_len); // 546
}














function	get_sil() {
	// ----------------------------------------------------------------------------
	// http://www-01.sil.org/iso639-3/download.asp
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading page from sil.org... ");
	$html = file_get_contents('http://www-01.sil.org/iso639-3/download.asp');
	printf("Done\n");

	// printf($html);
	$lines = explode("\n", $html);
	// printf("Count [%d]\n", count($lines));
	foreach ($lines as $line) {
		if (($pos = strpos($line, 'iso-639-3_Code_Tables_')) > 0) {
			$end = strpos($line, '"', $pos);
			$filename = substr($line, $pos, $end-$pos);
			// printf("Line [%s] [%d][%d]\n", $line, $pos, $end);
			// printf("FN [%s]\n", $filename);
			break;
		}
	}

	// printf("FN [%s]\n", $filename);
	printf("Downloading file... ");
	$bytes = file_put_contents("/tmp/" . $filename, fopen("http://www-01.sil.org/iso639-3/" . $filename, 'r'));
	printf("Done\n");
	// printf("Bytes [%s]\n", $bytes);
	if ($bytes > 0) {
		$cmd = sprintf("unzip -o -j -d /tmp /tmp/%s", $filename);
		exec($cmd, $output, $rc);
		unlink("/tmp/" . $filename);
	}

	$parts = explode("_", $filename);
	$parts2 = explode(".", $parts[3]);
// 	print_r($parts2);
	return $parts2[0];
}

function	get_ethnologue() {
	// ----------------------------------------------------------------------------
	// http://www.ethnologue.com/codes/download-code-tables
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading page from ethnologue.com... ");
	$html = file_get_contents('http://www.ethnologue.com/codes/download-code-tables');
	printf("Done\n");
	// printf($html);
	$lines = explode("\n", $html);
	// printf("Count [%d]\n", count($lines));
	foreach ($lines as $line) {
		if (($pos = strpos($line, 'Language_Code_Data_')) > 0) {
			$end = strpos($line, '"', $pos);
			$filename = substr($line, $pos, $end-$pos);
			// printf("Line [%s] [%d][%d]\n", $line, $pos, $end);
			// printf("FN [%s]\n", $filename);
			break;
		}
	}

	// printf("FN [%s]\n", $filename);
	printf("Downloading file... ");
	$bytes = file_put_contents("/tmp/" . $filename, fopen("http://www.ethnologue.com/codes/" . $filename, 'r'));
	printf("Done\n");
	// printf("Bytes [%s]\n", $bytes);
	if ($bytes > 0) {
		$cmd = sprintf("unzip -o -d /tmp /tmp/%s", $filename);
		exec($cmd, $output, $rc);
		unlink("/tmp/" . $filename);
		unlink("/tmp/readme.txt");
	}
}

function	get_iso_codes() {
	// http://anonscm.debian.org/gitweb/?p=iso-codes/iso-codes.git
	// https://alioth.debian.org/anonscm/git/iso-codes/iso-codes
	// https://alioth.debian.org/anonscm/git/iso-codes/iso-codes.git
	
	if (!is_dir("iso-codes")) {
		$cmd = "git clone https://alioth.debian.org/anonscm/git/iso-codes/iso-codes.git";
		printf("%s\n", str_repeat("-", 80));
		printf("Creating local iso-codes GIT repo for the first time. Please wait...\n");
		exec($cmd, $output, $rc);
	}	
	$rc = chdir("iso-codes");
	if ($rc === TRUE) {
		printf("%s\n", str_repeat("-", 80));
		printf("Updating iso-codes repo...\n");
		$cmd = sprintf("git pull");
		exec($cmd, $output, $rc);
		// printf("RC = [%d]\n", $rc);
		// print_r($output);
		chdir ("..");
	}
	
}


function	import_iso_codes($db = NULL) {
	printf("%s\n", str_repeat("-", 80));
	printf("Import iso-codes into database...\n");


	$iso = get_iso_config(); // defines a number of ISO type information

	foreach ($iso as $iso_code => $data) {
		$file_size = filesize($data['path']);
		printf("  Importing %s information... [%s bytes]\n", $data['name'], number_format($file_size));

		if ($data['enabled'] === FALSE) {
			printf("    -- This import is disabled in code.\n");
			continue;
		}

		if (FALSE === mysqli_query($db, sprintf("TRUNCATE `%s`;", $data['table_name']))) {
			printf("TRUNCATE failed on [%s]\n", $data['table_name']);
			printf("Error: %s\n", mysqli_error($db));
			return FALSE;
		}

		$unique_field_name = $data['unique_field'];
		
		
		// $html = file_get_html($data['path']); // parses XML into an object
		$html = file_get_contents($data['path']); // gets the raw XML
		if (!$html) {
			printf("Could not open [%s]\n", $data['path']);
			continue;
		}

	/*  simplexml_load_file() */


		// printf("1\n");
		$stuff = new SimpleXMLElement($html);
		// printf("2\n");
		// $entries1 = $stuff->iso_3166_3_entry;
		// print_r($entries[0]); exit;
		
		// printf("Count [%d][%d]\n", count($entries), count($entries1)); exit;
		
		// initialize empty array to store the data array from each row
		$theData = array();

		// loop over rows
		// foreach($entries as $entry) {
		$count = count($stuff->$data['entry']);
		// printf("Count1 [%d]\n", $count);
		for ($x = 0; $x < $count || $x == 10; $x++) {
			// for each row...
			
			$entry = $stuff->{$data['entry']}[$x];
			$atts_object = $entry->attributes();
			$atts_array = (array)$atts_object;
			$attributes = $atts_array['@attributes'];
			
			// printf("Attributes :%s\n", print_r($attributes, TRUE)); exit;
			
			
			if (!empty($attributes[$unique_field_name])) {
				$unique_field_data = $attributes[$unique_field_name];
			}
			else {
				printf("Bail 1\n");
				continue;
			}

			// printf("3\n");
			foreach ($data['fields'] as $field_name) {
				// for each field
				// normalize the field data
				if (!empty($attributes[$field_name])) {
					$theData[$unique_field_data][$field_name] = $attributes[$field_name];
				}
				else {
					$theData[$unique_field_data][$field_name] = NULL;
				}
			} // looping over each filed in this one entry
			// all fields in this row processed
			// printf("4\n");

			// print_r($theData); die();


		} // looping over every entry
		// printf("5\n");
		// all of the rows processed in this one file
		// print_r($theData); exit;
		
		$count = 0;
		foreach($theData as $unique => $imp_data) {
			// once per unique entry
			$field_names = array();
			$field_datas = array();
			foreach($imp_data as $field_name => $field_data) {
				$field_names[] = $field_name;
				$field_datas[] = mysql_real_escape_string($field_data);
			}
		

			$sql = sprintf("INSERT INTO %s (%s) VALUE ('%s');", $data['table_name'], implode(",", $field_names), implode("','", $field_datas));
			// print_r($sql . "\n"); exit;
			if (FALSE === mysqli_query($db, $sql)) {
				printf("Insert [1] failed.\n");
				printf("SQL = %s\n", $sql);
				return FALSE;
			}
			else {
				$count++;
				if ($count % 10 == 0) {
					printf(".");
				}
			}

		}
		
		printf("\n");
		
		
		// unset($html);
		// unset($stuff);

		// Write to the database for this iso class

	}
	// all of the files processed

}



function	get_iso_config() {
	$iso = array();
	$base = 'iso-codes/';	
	
	$iso['iso_3166'] = array(
		'name' => 'iso_3166',
		'enabled' => TRUE,
		'description' => 'list of all countries in the ISO 3166',
		'path' => $base . 'iso_3166/iso_3166.xml',
		'table_name' => 'iso_3166',
		'entry' => 'iso_3166_entry',
		'fields' => array('alpha_2_code','alpha_3_code','alpha_4_code','numeric_code','common_name','name','official_name','date_withdrawn','names'),
		'unique_field' => 'name',
	);
	$iso['iso_639'] = array(
		'name' => 'iso_639',
		'enabled' => TRUE,
		'description' => 'list of all languages in the ISO 639',
		'path' => $base . 'iso_639/iso_639.xml',
		'table_name' => 'iso_639',
		'entry' => 'iso_639_entry',
		'fields' => array('iso_639_2B_code','iso_639_2T_code','iso_639_1_code','name','common_name'),
		'unique_field' => 'name',
	);
	$iso['iso_639_3'] = array(
		'name' => 'iso_639_3',
		'enabled' => TRUE,
		'description' => 'list of all languages in the ISO 639-3',
		'path' => $base . 'iso_639_3/iso_639_3.xml',
		'table_name' => 'iso_639_3',
		'entry' => 'iso_639_3_entry',
		'fields' => array('id','part1_code','part2_code','status','scope','type','inverted_name','reference_name','name','common_name'),
		'unique_field' => 'id',
	);
	$iso['iso_639_5'] = array(
		'name' => 'iso_639_5',
		'enabled' => TRUE,
		'description' => 'list of all languages in the ISO 639-5',
		'path' => $base . 'iso_639_5/iso_639_5.xml',
		'table_name' => 'iso_639_5',
		'entry' => 'iso_639_5_entry',
		'fields' => array('id','parents','name'),
		'unique_field' => 'id',
	);
	$iso['iso_4217'] = array(
		'name' => 'iso_4217',
		'enabled' => TRUE,
		'description' => 'list of all currencies in the ISO 4217',
		'path' => $base . 'iso_4217/iso_4217.xml',
		'table_name' => 'iso_4217',
		'entry' => 'iso_4217_entry',
		'fields' => array('letter_code','numeric_code','currency_name'),
		'unique_field' => 'letter_code',
	);
	$iso['iso_15924'] = array(
		'name' => 'iso_15924',
		'enabled' => TRUE,
		'description' => 'list of all script names in the ISO 15924',
		'path' => $base . 'iso_15924/iso_15924.xml',
		'table_name' => 'iso_15924',
		'entry' => 'iso_15924_entry',
		'fields' => array('alpha_4_code','numeric_code','name'),
		'unique_field' => 'name',
	);

	$iso['iso_3166_2'] = array(
		'name' => 'iso_3166_2',
		'enabled' => FALSE,
		'description' => 'list of all country subdivisions in the ISO 3166-2',
		'path' => $base . 'iso_3166_2/iso_3166_2.xml',
		'table_name' => 'iso_3166_2',
		// 'entry' => 'iso_15924_entry',
		// 'fields' => array('alpha_4_code','numeric_code','name'),
		'unique_field' => '',
	);

	return $iso;
}




function	make_tables() {
$link = mysqli_connect("localhost", "root", "Trees1Trees!", "language_data");

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
	
	$iso = get_iso_config(); // defines a number of ISO type information
	foreach ($iso as $iso_code => $data) {
		if ($data['enabled'] === TRUE) {
			$fields = implode($data['fields'], " VARCHAR(250), ") . " VARCHAR(250)";
			$sql = sprintf("CREATE TABLE %s (%s, INDEX (%s))", $data['table_name'], $fields, $data['unique_field']);
			printf("SQL [%s]\n", $sql);
		}
	}
}



function	get_native_names() {
	// ----------------------------------------------------------------------------
	// http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
	printf("%s\n", str_repeat("-", 80));
	printf("Downloading native names from Wikipedia... ");
	$html = new simple_html_dom();
	$html = file_get_html('http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes');
	if (!$html) {
		printf("Could not open [%s]\n", $data['path']);
		return array();
	}

	$table = $html->find('table',1);
	$rows = array();
	foreach ($table->find('tr') as $tr) {
		$tds = array();
		foreach ($tr->find('td') as $td) {
			$tds[] = strip_tags($td);
		}
		if (count($tds) == 10) {
			$rows[] = array(
				'language_family' => $tds[1],
				'language_name' => $tds[2],
				'native_name' => $tds[3],
				'639-1' => $tds[4],
				'639-2t' => $tds[5],
				'639-2b' => $tds[6],
				'639-3' => $tds[7],
				'639-9' => $tds[8],
				'notes' => $tds[9],
			);
		}
	}

	printf("Done\n");
	return $rows;
}

function	write_native_names ($db, $table_name = 'native_names', $nn = array()) {
	if (empty($nn)) {
		return FALSE;
	}

	printf("Importing native names...\n");
	// START TRANSACTION;
	if (FALSE === mysqli_query($db, "START TRANSACTION;")) {
		printf("START failed\n");
		printf("Error: %s\n", mysqli_error($db));
		return FALSE;
	}

	if (FALSE === mysqli_query($db, sprintf("TRUNCATE `%s`;", $table_name))) {
		printf("TRUNCATE failed\n");
		printf("Error: %s\n", mysqli_error($db));
		return FALSE;
	}
	
	// printf("Count [%s]\n", count($nn));

	foreach ($nn as $key => $data_a) {
		$col_names = array();
		$line = array();
		foreach($data_a as $name => $data) {
			$col_names[] = $name;
			$line[] = mysqli_real_escape_string($db, ($data));
		}
		$col_names = "`" . implode("`,`", $col_names) . "`";
		$line_data = '"' . trim(implode('","', $line)) . '"';
		$sql = sprintf("INSERT INTO %s (%s) VALUE (%s);", $table_name, $col_names, $line_data);
		// printf("SQL = %s\n", $sql);
		if (FALSE === mysqli_query($db, $sql)) {
			printf("Insert [2] failed.\n");
			printf("SQL = %s\n", $sql);
			printf("Error: %s\n", mysqli_error($db));
			return FALSE;
		}
	}

	// COMMIT;
	if (FALSE === mysqli_query($db, "COMMIT;")) {
		printf("COMMIT failed\n");
		return FALSE;
	}
}










/*
CREATE TABLE pet (name VARCHAR(20), owner VARCHAR(20),
    species VARCHAR(20), sex CHAR(1), birth DATE, death DATE);
*/
    
/*
ISO 3166 (iso_3166/iso_3166.xml)
========

This lists the 2-letter country code and "short" country name. The
official ISO 3166 maintenance agency is ISO. The gettext domain is
"iso_3166".
<http://www.iso.org/iso/country_codes>


ISO 639 (iso_639/iso_639.xml)
=======

This lists the 2-letter and 3-letter language codes and language
names. The official ISO 639 maintenance agency is the Library of
Congress. The gettext domain is "iso_639".
<http://www.loc.gov/standards/iso639-2/>


ISO 639-3 (iso_639_3/iso_639_3.xml)
=========

This is a further development of ISO 639-2, see above. All codes
of ISO 639-2 are included in ISO 639-3. ISO 639-3 attempts to
provide as complete an enumeration of languages as possible,
including living, extinct, ancient, and constructed languages,
whether major or minor, written or unwritten. The gettext
domain is "iso_639_3". The official ISO 639-3 maintenance agency
is SIL International.
<http://www.sil.org/iso639-3/>

ISO 4217 (iso_4217/iso_4217.xml)
========

This lists the currency codes and names. The official ISO 4217
maintenance agency is the British Standards Institution. The
gettext domain is "iso_4217".
<http://www.bsi-global.com/en/Standards-and-Publications/Industry-Sectors/Services/BSI-Currency-Code-Service/>


ISO 15924 (iso_15924/iso_15924.xml)
=========

This lists the language scripts names. The official ISO 15924
maintenance agency is the Unicode Consortium. The gettext
domain is "iso_15924".
<http://unicode.org/iso15924/>


ISO 3166-2 (iso_3166_2/iso_3166_2.xml)
==========

The ISO 3166 standard includes a "Country Subdivision Code",
giving a code for the names of the principal administrative
subdivisions of the countries coded in ISO 3166. The official
ISO 3166-2 maintenance agency is ISO. The gettext domain is
"iso_3166_2".
<http://www.iso.org/iso/country_codes/background_on_iso_3166/iso_3166-2.htm>


Tracking updates to the various ISO standards
=============================================

Below is a list of websites we use to check for updates to the
standards. Please note that ISO 4217 is missing, because the BSI
does not provide a list of changes.

ISO 3166 and ISO 3166-2:
http://www.iso.org/iso/country_codes/check_what_s_new.htm
http://www.iso.org/iso/country_codes/updates_on_iso_3166.htm
http://www.iso.org/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1-semic.txt

ISO-639:
http://www.loc.gov/standards/iso639-2/php/code_changes.php

ISO 639-3:
http://www.sil.org/iso639-3/codes.asp?order=639_3&letter=%25

ISO-15924:
http://unicode.org/iso15924/codechanges.html


*/
