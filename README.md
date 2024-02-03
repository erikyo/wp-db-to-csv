# wp-db-to-csv

A simple plugin to update a WordPress table using CSV data.

## Overview

**WP db-to-csv** is a WordPress plugin designed to facilitate the update of a WordPress table using CSV data. The plugin provides a user-friendly interface for downloading and updating data in a specified table.

## Installation
1. Download the plugin ZIP file.
2. Upload the ZIP file through the WordPress admin interface or extract it directly into the `wp-content/plugins/` directory.
3. Activate the plugin through the WordPress Plugins menu.

## Usage

### Download CSV
1. Navigate to the **CSV Update Plugin** menu in the WordPress admin dashboard.
2. Select the target table (without prefix) from the dropdown menu.
3. Click the **Download** button.
4. The CSV file containing the data from the selected table will be downloaded.

### Update Database
1. Navigate to the **CSV Update Plugin** menu in the WordPress admin dashboard.
2. Choose the target table (without prefix) from the dropdown menu.
3. Configure additional options:
  - **Drop Table on Update:** If checked, the existing table will be dropped before inserting new data.
  - **Skip the first row:** If checked, the first row of the CSV data will be skipped.
  - **CSV Separator:** Specify the CSV separator (default is `,`).
4. Paste the CSV data into the provided textarea.
5. Click the **Update Database** button.
