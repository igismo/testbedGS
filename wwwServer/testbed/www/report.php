<?

include('defs.php');

$user = CheckLoginOrDie();
$isadmin = ISADMIN();

if (!$isadmin)
    USERERROR("You do not have permission to view this page");

$opt = OptionalPageArguments(
    "query", PAGEARG_ANYTHING
);

PAGEHEADER("SQL Report");

echo "SQL Query:<br />\n";
echo "<form action=\"report.php\" method=\"post\">\n";
echo "<textarea name=\"query\" rows=\"10\" cols=\"80\">\n";
if (isset($query)) echo htmlspecialchars($query);
echo "</textarea><br />\n";
echo "<input type=\"submit\" value=\"Execute\" />\n";
echo "</form>\n";

# show the query results
if (isset($query)) {
    echo "<hr />\n";

    $query_result = DBWarn($query, NULL);
    if (!$query_result)
        echo "<b>Invalid query</b>";
    else if (mysql_num_rows($query_result) == 0)
        echo "<b>Empty result</b>\n";
    else {
        $cols = mysql_num_fields($query_result);

        echo "<table style=\"margin: auto\">\n<tr>\n";
        for ($i = 0; $i < $cols; ++$i)
            echo "<th>" . mysql_field_name($query_result, $i) . "</th>\n";
        echo "</tr>\n";

        while ($row = mysql_fetch_array($query_result)) {
            echo "<tr>\n";
            for ($i = 0; $i < $cols; ++$i)
                echo "<td>" . htmlspecialchars($row[$i]) . "</td>";
            echo "</tr>\n";
        }

        echo "</table>\n";
    }
}

PAGEFOOTER();

?>
