<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<?php
	include './definitions.php';
	
	$jobid = $_GET["id"];
	$csv = jobid2csv($jobid);
	cachezip(jobid2remote($jobid),$csv);
	$scorefile = jobid2scorefile($jobid);

	function str2result($str) {
		if( $str == "YES" ) {
			return 1;
		}
		if( $str == "NO" ) {
			return -1;
		}
		return 0;
	}
	function result2str($result) {
		if( $result == -1 ) {
			return "NO";
		}
		if( $result == 1 ) {
			return "YES";
		}
		return "MAYBE";
	}
?>
</head>
<body>

<table>
<?php
	$file = new SplFileObject($csv);
	$file->setFlags( SplFileObject::READ_CSV );
	$records = [];
	foreach( $file as $row ) {
	  if( !is_null($row[0]) ) {
	    $records[] = $row;
	  }
	}
	unset( $records[0] );

	$solvers = [];
	$solver = $records[1][3];
	$solverid = $records[1][4];
	$firstsolver = $solver;
	$i = 1;
	do {
		$solvers[$solver] = [ "id" => $solverid, "score" => 0 ];
		$lastsolver = $solver;
		$i++;
		$solver = $records[$i][3];
		$solverid = $records[$i][4];
	} while( $solver != $firstsolver );

	echo " <tr>\n";
	echo "  <th>benchmark</th>\n";
	foreach( array_keys($solvers) as $solver ) {
		echo "  <th>$solver</th>\n";
	}
	echo " <tr><th>\n";
	$bench = [];

	foreach( $records as $record ) {
		$solver = $record[3];
		if( $solver == $firstsolver ) {
			$bench = [];
			$benchmark = parse_benchmark( $record[1] );
			$url = bmid2url($record[2]);
		}
		$result = str2result($record[11]);
		$bench[$solver] = [
			"result" => $result,
			"time" => parse_time($record[9]),
			"cpu" => parse_time($record[8]),
			"pair" => $record[0],
		];
		if( $solver == $lastsolver ) {
			$conflict = false;
			foreach( array_keys($bench) as $myname ) {
				$p = $bench[$myname];
				$score = abs($p["result"]);
				$solvers[$myname]["score"] += $score;
				foreach( $bench as $q ) {
					if( $p["result"] * $q["result"] < 0 ) {
						$conflict = true;
					}
				}
			}
			echo " <tr".( $conflict ? " class=conflict" : "" ).">\n";
			echo "  <td class=benchmark><a href='$url'>$benchmark</a></td>\n";
			foreach( array_keys($bench) as $myname ) {
				$p = $bench[$myname];
				$result = $p["result"];
				$url = pairid2remote($p["pair"]);
				echo "  <td " . result2style($result) . "><a href='$url'>" . result2str($result);
				echo "  <span class=time>" . $p["cpu"] . "/" . $p["time"] . "</span></a>";
			}
			echo " </tr>\n";
		}
	}
	echo " <tr><th>\n";
	$scorefileD = fopen($scorefile,"w");
	foreach( array_keys($solvers) as $solver ) {
		$score = $solvers[$solver]["score"];
		$id = $solvers[$solver]["id"];
		echo "  <th>$score</th>\n";
		fwrite( $scorefileD, "$solver,$id,$score\n" );
	}
	fclose( $scorefileD );
?>
</table>
</body>
</html>

