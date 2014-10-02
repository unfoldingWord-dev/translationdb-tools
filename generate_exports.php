<?php

// Generates export files

$link = mysqli_connect("localhost", "root", "Trees1Trees!", "language_data");

mysqli_set_charset($link, 'utf8');

if (!is_dir("exports")) {
	mkdir("exports");
}


// Get the additional languages
$add_langs = array();
$table_name = '`AdditionalLanguages`';
$sql = sprintf("SELECT COALESCE(NULLIF(TwoLetter, ''),NULLIF(ThreeLetter, ''),IETF_tag) as code,  COALESCE(NULLIF(native_name, ''),common_name) as name FROM %s ORDER BY code;", $table_name);
$result = mysqli_query($link, $sql);
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	$add_langs1[$row['code']] = $row['code'];
	$add_langs2[$row['code']] = $row['name'];
}


// -------------------------------------------------------------------------------------------------
/*
codes-d43.txt: a text file containing a single line of comma-separated IETF-compliant language tags with 2-letter codes for
languages that have them (e.g. "ar"), 3-letter codes for languages that have them (e.g. "ttt"), sorted alphabetically.
This will be used to populate the language selectors on Door43 (Dokuwiki). Example:

aa, aab, aac, ab, aba...
*/

// From the SIL table

$lines = array();

$table_name = '`ISO_639-3`';
$sql = sprintf("SELECT COALESCE(NULLIF(Part1, ''),Id) as code FROM %s WHERE Language_Type = 'L' AND Scope = 'I' ORDER BY code;", $table_name);
$export_fn = "exports/codes.txt";

$result = mysqli_query($link, $sql);
if (FALSE === $result) {
	printf("Select from %s failed.\n", $table_name);
	return FALSE;
}

while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	$langs[$row['code']] = $row['code'];
}

$langs = array_merge($langs, $add_langs1);
ksort($langs);

foreach ($langs as $lang) {
	$lines[] = $lang;
}



$line = implode(" ", $lines);
writeUTF8File($export_fn, $line);
mysqli_free_result($result);
printf("Wrote %s\n", $export_fn);


/* --------------------------------------------------------------------
// Not using this source as of 08-11-2014
// From the GIT table
$lines = array();
$table_name = '`iso_639_3`';
// $sql = sprintf("SELECT COALESCE(id,part1_code) as code FROM %s WHERE type = 'L' ORDER BY Id;", $table_name);
$sql = sprintf("SELECT COALESCE(part1_code,id) as code FROM %s WHERE type = 'L' ORDER BY part1_code,id;", $table_name);
$export_fn = "exports/codes-d43-git.txt";


$result = mysqli_query($link, $sql);
if (FALSE === $result) {
	printf("Select from %s failed.\n", $table_name);
	return FALSE;
}

while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	$lines[] = $row['code'];
}

$line = implode(",", $lines);
file_put_contents($export_fn, $line);
mysqli_free_result($result);
printF("Wrote %s\n", $export_fn);
--------------------------------------------------------------------- */

// exec("tar -czf exports/codes-d43.tar.gz exports/codes-d43-*.txt");








// -------------------------------------------------------------------------------------------------
/*
langnames.txt: the same codes as "codes-d43.txt" but with one code per line, followed by a <tab>, then the language selfname
(if known), then a comma, then the English name of the language. All language codes should be grouped by 2-letter country code,
with the English shortname of the country in parentheses. This will be used to configure Door43 to display the correct language
names. Note the # before the country code, to comment it out. Example:

# PK (Pakistan)  abc     Language Name
bal     ?????, Baluchi
*/



$lines = array();
$table_name = '`ISO_639-3`';
$sql = sprintf(
"SELECT COALESCE(NULLIF(x.Part1, ''),x.Id) as code, x.Ref_Name,
COALESCE(NULLIF(nn1.native_name, ''), NULLIF(nn2.native_name, ''), x.Ref_Name) as n_n  
FROM %s x 
LEFT JOIN native_names nn1 on x.Part1=nn1.`639-1` 
LEFT JOIN native_names nn2 on x.Id=nn1.`639-3` 
WHERE x.Language_Type = 'L' AND x.Scope = 'I' 
ORDER BY code;", $table_name);
$export_fn = "exports/langnames.txt";




$result = mysqli_query($link, $sql);
if (FALSE === $result) {
	printf("Select from %s failed.\n%s\n", $table_name, mysqli_error($link));
	printf("SQL [%s]\n", $sql);
	return FALSE;
}


$langs = array();
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	$langs[$row['code']] = $row['n_n'];
}
$langs = array_merge($langs, $add_langs2);
ksort($langs);

foreach ($langs as $code => $lang) {
	$lines[] = sprintf("%s\t%s", $code, $lang);
}



$line = implode("\n", $lines);
writeUTF8File($export_fn, $line);
mysqli_free_result($result);
printf("Wrote %s\n", $export_fn);











/*
[
  {   "lc": "en", 
      "ln": "English"
  },
  {   "lc": "es", 
      "ln": "Español"
  },
  {   "lc": "es-419", 
      "ln": "Español Latin America"
  }
]
*/
$lines = array();
$table_name = '`ISO_639-3`';
$sql = sprintf(
"SELECT COALESCE(NULLIF(x.Part1, ''),x.Id) as code, x.Ref_Name,
COALESCE(NULLIF(nn1.native_name, ''), NULLIF(nn2.native_name, ''), x.Ref_Name) as n_n  
FROM %s x 
LEFT JOIN native_names nn1 on x.Part1=nn1.`639-1` 
LEFT JOIN native_names nn2 on x.Id=nn1.`639-3` 
WHERE x.Language_Type = 'L' AND x.Scope = 'I' 
ORDER BY code;", $table_name);
$export_fn = "exports/langnames.json";




$result = mysqli_query($link, $sql);
if (FALSE === $result) {
	printf("Select from %s failed.\n", $table_name);
	return FALSE;
}

$langs = array();
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	$langs[$row['code']] = $row['n_n'];
}
$langs = array_merge($langs, $add_langs2);
ksort($langs);

$lines[] = "[\n";

$last_key = endKey($langs);

foreach ($langs as $code => $lang) {
	$lines[] = sprintf("  {  \"lc\": \"%s\",\n     \"ln\": \"%s\"\n  }%s\n", $code, $lang, ($code == $last_key ? "" : ","));
}


$lines[] = "]\n";

$line = implode("\n", $lines);
writeUTF8File($export_fn, $line);
mysqli_free_result($result);
printf("Wrote %s\n", $export_fn);




// -------------------------------------------------------------------------------------------------
mysqli_close($link);
// -------------------------------------------------------------------------------------------------



function writeUTF8File($filename,$content) { 
        $f=fopen($filename,"w"); 
        # Now UTF-8 - Add byte order mark 
        fwrite($f, pack("CCC",0xef,0xbb,0xbf)); 
        fwrite($f,$content); 
        fclose($f); 
}

// Returns the key at the end of the array
function endKey($array){
end($array);
return key($array);
}