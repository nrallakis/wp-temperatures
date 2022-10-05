<?php
/*
	Plugin Name: Temperatures
	Plugin URI:
	Description: This is a plugin to monitor water boiler temperature data from remote sensors and plot them as a function of time. 
	Author: Nicholas Rallakis & Tassos Stergiopoulos
	Version: 1.2
*/

function createTable()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'temperatures';
    $sql = "CREATE TABLE $table_name (
		id INT NOT NULL AUTO_INCREMENT,
		temp_1 DECIMAL(4,1),
		temp_2 DECIMAL(4,1),
		temp_3 DECIMAL(4,1),
		temp_4 DECIMAL(4,1),
		temp_5 DECIMAL(4,1),
		temp_6 DECIMAL(4,1),
		auto_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset_collate;";

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    return $sql;
}

function loadTemps($row)
{
    $temps = array();
    $temps[] = $row->temp_1;
    $temps[] = $row->temp_2;
    $temps[] = $row->temp_3;
    $temps[] = $row->temp_4;
    $temps[] = $row->temp_5;
    $temps[] = $row->temp_6;
    return $temps;
}

function deleteOldData()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'temperatures';
    $wpdb->query("
		DELETE FROM $table_name	
		WHERE auto_date < NOW() - INTERVAL 7 DAY;");
}

function getData()
{
    $interval = 1;
    $timeframe = $_GET["timeframe"];
    switch ($timeframe)
    {
        case "lastday":
            $interval = 1;
        break;
        case "last2days":
            $interval = 2;
        break;
        case "lastweek":
            $interval = 7;
        break;
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'temperatures';
    $myrows = $wpdb->get_results("
		SELECT * FROM $table_name
		WHERE auto_date >= NOW() - INTERVAL " . $interval . " DAY;");

    $all_data = array();
    for ($x = 1;$x <= 6;$x++)
    {
        $js_data = "";
        foreach ($myrows as $row)
        {
            $temps = loadTemps($row);
            $js_data .= sprintf("{x: new Date('%s'), y: %d},", $row->auto_date, $temps[$x - 1]);
        }
        $all_data[] = $js_data;
    }
    return $all_data;
}

function insertCurrentTemps($temp_1, $temp_2, $temp_3, $temp_4, $temp_5, $temp_6)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'temperatures';
    $wpdb->insert($table_name, array(
        'temp_1' => $temp_1,
        'temp_2' => $temp_2,
        'temp_3' => $temp_3,
        'temp_4' => $temp_4,
        'temp_5' => $temp_5,
        'temp_6' => $temp_6,
    ));
}

function download_page($path)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $retValue = curl_exec($ch);
    curl_close($ch);
    return $retValue;
}

function fetchAndInsertTemperatures()
{
    // 	//	BOARD N1
    $sXML = download_page('http://mirabella.gotdns.com:81/status.xml');
    $xml = simplexml_load_string($sXML) or die("ERROR BOARD 1 (http://mirabella.gotdns.com:81/status.xml)");
    $temp_1 = str_replace("°C", "", $xml->Temperature1);
    $temp_2 = str_replace("°C", "", $xml->Temperature2);

    //BOARD N2
    $sXML = download_page('http://mirabella.gotdns.com:83/status.xml');
    $xml = simplexml_load_string($sXML) or die("ERROR BOARD 2 (http://mirabella.gotdns.com:83/status.xml)");
    $temp_3 = str_replace("°C", "", $xml->Temperature1);
    $temp_4 = str_replace("°C", "", $xml->Temperature2);

    //BOARD N3
    $sXML = download_page('http://mirabella.gotdns.com:82/status.xml');
    $xml = simplexml_load_string($sXML) or die("ERROR BOARD 3 (http://mirabella.gotdns.com:82/status.xml)");
    //swapped sensors
    $temp_5 = str_replace("°C", "", $xml->Temperature2);
    $temp_6 = str_replace("°C", "", $xml->Temperature1);

    insertCurrentTemps($temp_1, $temp_2, $temp_3, $temp_4, $temp_5, $temp_6);
}

function woocsp_schedules($schedules)
{
    if (!isset($schedules["2s"]))
    {
        $schedules["2s"] = array(
            'interval' => 120,
            'display' => __('Once every 2 minute')
        );
    }
    return $schedules;
}

//Add cron schedules filter with upper defined schedule.
add_filter('cron_schedules', 'woocsp_schedules');

//Custom function to be called on schedule triggered.
function scheduleTriggered()
{
    fetchAndInsertTemperatures();
}
add_action('temperature_cron_delivery', 'scheduleTriggered');

function getConfig()
{
    $timeframe = $_GET["timeframe"];
    switch ($timeframe)
    {
        case "lastday":
            return "configLastDay";
        case "last2days":
            return "configLast2Days";
        case "lastweek":
            return "configLastWeek";
    }
}

// function that runs when shortcode is called
function temp_plot_shortcode()
{
    $data = getData();
    deleteOldData();
    fetchAndInsertTemperatures();
?>
	<link rel="stylesheet" href="//cdn.jsdelivr.net/chartist.js/latest/chartist.min.css">
	<script src="//cdn.jsdelivr.net/chartist.js/latest/chartist.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<style>
		h1 {
			text-align: center;
		}
		.ct-series-a .ct-bar, .ct-series-a .ct-line, .ct-series-a .ct-point, .ct-series-a .ct-slice-donut {
    		stroke: red;
		}		
		.ct-series-b .ct-bar, .ct-series-b .ct-line, .ct-series-b .ct-point, .ct-series-b .ct-slice-donut {
    		stroke: blue;
		}
	</style>
	
	<h1>
	  Rooms 11 and 12 
	  <span style="color: red">(red)</span>
	  Rooms 13 and 14  
	  <span style="color: blue">(blue)</span> 
	</h1>
	<div class="ct-chart-1 ct-golden-section"></div>
	
	<h1>
	  Rooms 15 and 16 
	  <span style="color: red">(red)</span>
	  Rooms 17 and 18  
	  <span style="color: blue">(blue)</span> 
	</h1>
	<div class="ct-chart-2 ct-golden-section"></div>
	
	<h1>
	  Rooms 21 to 23
	  <span style="color: red">(red)</span>
	  Rooms 24 to 28  
	  <span style="color: blue">(blue)</span> 
	</h1>
	<div class="ct-chart-3 ct-golden-section"></div>

	<script type="text/javascript">
		let configLastWeek = {
			axisX: {
				type: Chartist.FixedScaleAxis,
				divisor: 7,
				labelInterpolationFnc: function(value) {
					return moment(value).format('D/MM');
				}
			},
			showPoint: false
		}
		let configLastDay = {
			axisX: {
				type: Chartist.FixedScaleAxis,
				divisor: 8,
				labelInterpolationFnc: function(value) {
					return moment(value).format('HH A');
				}
			},
			showPoint: false
		}
		let configLast2Days = {
			axisX: {
				type: Chartist.FixedScaleAxis,
				divisor: 8,
				labelInterpolationFnc: function(value) {
					return moment(value).format('D/MM  HH A');
				}
			},
			showPoint: false
		}

		let config = <?php echo getConfig(); ?>;

		var chart = new Chartist.Line('.ct-chart-<?php echo 1 ?>', {
			series: [
				{
					name: 'series-<?php echo 1 ?>',
					data: [
						<?php echo ($data[0]); ?>
					]
				},
				{
					name: 'series-<?php echo $x ?>',
					data: [
						<?php echo ($data[3]); ?>
					]
				}
			]
		}, config);

		var chart = new Chartist.Line('.ct-chart-<?php echo 2 ?>', {
			series: [
				{
					name: 'series-<?php echo 2 ?>',
					data: [
						<?php echo ($data[1]); ?>
					]
				},
				{
					name: 'series-<?php echo 2 ?>',
					data: [
						<?php echo ($data[4]); ?>
					]
				}
			]
		}, config);

		var chart = new Chartist.Line('.ct-chart-<?php echo 3 ?>', {
			series: [
				{
					name: 'series-<?php echo 3 ?>',
					data: [
						<?php echo ($data[5]); ?>
					]
				},
				{
					name: 'series-<?php echo 3 ?>',
					data: [
						<?php echo ($data[2]); ?>
					]
				}
			]
		}, config);
	</script>
<?php
}
// register shortcode
add_shortcode('temp_plot', 'temp_plot_shortcode');

register_activation_hook(_FILE_, 'onActivation');
function onActivation()
{
    createTable();

    if (!wp_get_schedule('temperature_cron_delivery'))
    {
        wp_schedule_event(time() , '2s', 'temperature_cron_delivery');
    }
}

// Deactivate scheduled events on plugin deactivation.
register_deactivation_hook(_FILE_, 'woocsp_deactivation');
function woocsp_deactivation()
{
    wp_clear_scheduled_hook('temperature_cron_delivery');
}

