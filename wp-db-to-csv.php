<?php
/*
Plugin Name: WP db-to-csv
Description: A simple plugin to update a WordPress table using CSV data.
Version: 1.0
License: GPLv2 or later
Author: Erik Golinelli <erik@codekraft.it>
*/

function download_csv($main_table_name) {
	global $wpdb;
	/** @var array $outfile download the CSV file of the table */
	$outfile= $wpdb->get_results('SELECT * FROM '.$main_table_name, ARRAY_A);

	$csv = '';

	/** @var resource|string $out convert the array to CSV */
	if (!empty($outfile) && is_array($outfile)) {
		/** @var resource $out the CSV file content */
		$out = fopen( 'php://temp', 'r+' );
		// Write the CSV data to the memory stream
		foreach ($outfile as $value) {
			fputcsv($out, $value);
		}

		// Rewind the stream pointer to the beginning
		rewind($out);

		// Read the CSV content into the variable
		while (!feof($out)) {
			$csv .= fgets($out);
		}

		// Close the stream resource
		fclose($out);
	} else {
		echo 'Can\'t download. Data not an array';
		return false;
	}

	if (empty($csv)) {
		echo 'Can\'t download. No data';
		return false;
	} else {
		echo '<script>alert("Backup Data from ' . $main_table_name . ' will be downloaded to CSV");</script>';
	}
	echo "<script>
function download(filename, base64text) {
  var element = document.createElement('a');
  element.setAttribute('href', 'data:text/csv;base64,77u/' + base64text);
  element.setAttribute('download', filename);

  element.style.display = 'none';
  document.body.appendChild(element);

  element.click();

  document.body.removeChild(element);
}

download('" . htmlspecialchars($main_table_name, ENT_QUOTES) . ".csv', '" . base64_encode($csv) . "');
</script>";

	return true;
}

// Function to process CSV data and update the database
function process_csv_data() {

	global $wpdb;

	// Get the CSV data from the textarea
	$table_name = sanitize_textarea_field( $_POST['table_name'] );
	$separator  = sanitize_textarea_field( $_POST['separator'] );
	$drop_table  = sanitize_textarea_field( $_POST['drop_table'] );
	$skip_first  = isset( $_POST['skip_first'] );
	$csv_data   = stripslashes( $_POST['csv_data'] ); // Use stripslashes to unescape the data

	// Replace <br> tags with newline character
	$csv_data = html_entity_decode( $csv_data );

	// Split CSV data into an array
	$csv_lines = explode( "\n", $csv_data );

	// Check if CSV data is empty
	if (empty($csv_lines) ) {
		echo 'no data found';
		return;
	}

	// Remove the first line and save the header in an array
	if ($skip_first) {
		array_shift( $csv_lines );
	}

	// Truncate the table before inserting new data
	if ($drop_table){
		$wpdb->query( "TRUNCATE TABLE $table_name" );
	}

	// Get the table columns dynamically
	$columns_info = $wpdb->get_results( "DESC $table_name", ARRAY_A );

	// Extract column names from the result
	$columns = array_column( $columns_info, 'Field' );

	// Prepare an array to hold the values to be inserted
	foreach ( $csv_lines as $csv_line ) {
		$csv_values = str_getcsv( $csv_line, $separator );
		// Escape and format the remaining values using prepared statement
		$field_values = array_map( function ( $value, $index ) use ( $wpdb, $columns_info ) {
			$column_type = strtolower( $columns_info[ $index ]['Type'] );

			// Handle datetime format for columns that are datetime type
			if ( strpos( $column_type, 'datetime' ) !== false ) {
				$value = ! empty( $value ) ? date( 'Y-m-d H:i:s', strtotime( $value ) ) : null;

				return $wpdb->prepare( '%s', $value );
			} elseif ( strpos( $column_type, 'int' ) !== false ) {
				// Handle numeric values
				$value = floatval( $value );

				return $wpdb->prepare( '%d', $value );
			} elseif ( strpos( $column_type, 'float' ) !== false || strpos( $column_type, 'decimal' ) !== false ) {
				// Handle numeric values
				$value = floatval( $value );

				return $wpdb->prepare( '%f', $value );
			} else {
				// Handle string values
				$value = trim( $value );
			}

			return $wpdb->prepare( '%s', esc_sql( $value ) );
		}, $csv_values, array_keys( $csv_values ) );

		// Combine field names and values into a string
		$fields = implode( ', ', $columns );
		$values = implode( ', ', $field_values );

		if ( count( $columns ) !== count( $field_values ) ) {
			echo '<p>Columns do not match</p>';
			echo '<p>Columns: ' . print_r( $columns, true ) . '</p>';
			echo '<p>Values: ' . print_r( $field_values, true ) . '</p>';
			echo '<p>SQL: ' . print_r( "INSERT INTO $table_name ($fields) VALUES ($values) ON DUPLICATE KEY UPDATE $fields", true ) . '</p>';
			break;
		} else {
			// Add the data to the table or replace it if it already exists
			$wpdb->query( "
				INSERT INTO $table_name ($fields)
				VALUES ($values)
			" );
		}
	}

	// Display a success message
	echo '<p>Database updated successfully! '. count($csv_lines) .' rows inserted</p>';
}

// Add an admin menu page
function csv_update_plugin_menu() {
	add_menu_page( 'CSV Update Plugin', 'CSV Update Plugin', 'manage_options', 'csv_update_plugin', 'csv_update_plugin_page' );
}
add_action( 'admin_menu', 'csv_update_plugin_menu' );

/**
 * Callback function to display the admin page content
 * @since 1.0
 */
function csv_update_plugin_page() {
	// get the list of tables
	global $wpdb;

	// Get the list of tables
	$tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );

	// build a select box with the list of tables
	$select = '<select name="table_name">';
	foreach ( $tables as $table ) {
		$select .= '<option value="' . $table[0] . '">' . $table[0] . '</option>';
	}
	$select .= '</select>';

	?>
	<div class="wrap">
		<h2>CSV Update Plugin</h2>

		<form method="post" action="" style="display: flex; flex-direction: column; gap: .5rem; max-width: 600px">
			Table Name (without prefix)
			<?php echo $select; ?>
			<label for="drop_table">
			Drop Table on Update <input name="drop_table" checked type="checkbox"/>
			</label>
			<label for="skip_first">
			Skip the first row <input name="skip_first" type="checkbox"/>
			</label>
			</label>
			CSV Separator
			<input name="separator" value="," type="text"/>
			</label>
			<label for="csv_data">Enter CSV Data:</label>
			<textarea name="csv_data" rows="5" cols="40"></textarea>
			<input type="submit" name="download" value="download" />
			<input type="submit" name="submit_csv" class="button-primary" value="Update Database">
		</form>
	</div>
	<?php

	// the download button
	if ( isset( $_POST['download'] ) ) {
		$table_name = sanitize_textarea_field( $_POST['table_name'] );
		$download   = sanitize_text_field( $_POST['download'] );
		if ($download) download_csv($table_name);
	}
	// the submit button
	if ( isset( $_POST['submit_csv'] ) ) {
		// Process the CSV data and update the database
		process_csv_data();
	}
}

/**
 * Output the CSV update form
 *
 * @return false|string
 */
function csv_update_form_shortcode() {
	ob_start();
	return ob_get_clean();
}
add_shortcode( 'csv_update_form', 'csv_update_form_shortcode' );
