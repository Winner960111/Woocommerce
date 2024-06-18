potential abusers button not working

triggered by not working

no logging of cron execution

### Installation and Usage

**Prerequisites:**
- WooCommerce plugin installed and activated.
- A valid SpeedyIndex API key.

**Installation:**
1. Upload the `rapid-url-indexer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure WooCommerce is installed and activated.

**Setting Up Credits Products:**
1. Go to WooCommerce > Products > Add New.
2. Create a new product for each credit bundle you want to offer (e.g., "100 Indexing Credits", "500 Indexing Credits", etc.).
3. Set the product type to "Simple Product".
4. Set the price for the credit bundle.
5. In the "Product Data" section, go to the "Advanced" tab and add a custom field with the name `_credits_amount` and the value equal to the number of credits in the bundle.
6. Publish the products.

**Configuring the Plugin:**
1. Go to the Rapid URL Indexer settings page in the WordPress admin area.
2. Enter your SpeedyIndex API key.
3. Save changes.

**How Credits are Added When the Product is Purchased:**
1. When a customer purchases the "Indexing Credits" product, the plugin will automatically detect the product using the custom field `_is_credits_product`.
2. The number of credits purchased will be added to the customer's account based on the quantity purchased.
3. The credits will be displayed in the customer's account under the "My Projects" section.

**Dependencies:**
- WooCommerce: For managing products and purchases.
- SpeedyIndex API: For URL indexing services.

**Using the Plugin:**
1. Purchase credits through the WooCommerce store.
2. Navigate to the "My Projects" section in the customer area to submit URLs for indexing.
3. Manage and view the status of your projects from the same section.

## API Documentation

The Rapid URL Indexer plugin provides a RESTful API for customers to interact with their projects and credits programmatically. The following endpoints are available:

### Authentication

All API endpoints require authentication using an API key. Customers can find their API key in the "My Projects" section of their account. The API key should be included in the `X-API-Key` header of each request.

### Endpoints

#### Submit URLs

- **Endpoint:** `POST /api/v1/projects`
- **Description:** Submit a new project for indexing.
- **Parameters:**
  - `project_name` (string, required): The name of the project.
  - `urls` (array, required): An array of URLs to be indexed.
  - `notify_on_status_change` (boolean, optional): Whether to receive email notifications when the project status changes. Defaults to `false`.
- **Response:** Confirmation of project creation and the unique project ID.

#### Get Project Status

- **Endpoint:** `GET /api/v1/projects/{project_id}`
- **Description:** Get the status of a specific project.
- **Parameters:**
  - `project_id` (integer, required): The ID of the project.
- **Response:** Project status, number of submitted and indexed links.

#### Download Project Report

- **Endpoint:** `GET /api/v1/projects/{project_id}/report`
- **Description:** Download the report for a specific project.
- **Parameters:**
  - `project_id` (integer, required): The ID of the project.
- **Response:** CSV file download containing the URLs and their indexing statuses.

#### Get Credits Balance

- **Endpoint:** `GET /api/v1/credits/balance`
- **Description:** Get the current credit balance for the authenticated user.
- **Response:** The current credit balance.

### Error Responses

If an error occurs, the API will return an appropriate HTTP status code along with an error message in the response body. Possible error codes include:

- **400 Bad Request** - The request was malformed or missing required parameters.
- **401 Unauthorized** - The API key is missing or invalid.
- **404 Not Found** - The requested resource (e.g., project) does not exist.
- **500 Internal Server Error** - An unexpected error occurred on the server.

### Detailed Description for WooCommerce Extension: URL Indexing Credit System

## Testing the RESTful API with curl

### Submit URLs
```sh
curl -X POST https://yourdomain.com/wp-json/rui/v1/projects -H "X-API-Key: your_api_key" -H "Content-Type: application/json" -d '{"project_name": "My Project", "urls": ["http://example.com", "http://example.org"], "notify_on_status_change": true}'
```

### Get Project Status
```sh
curl -X GET https://yourdomain.com/wp-json/rui/v1/projects/{project_id} -H "X-API-Key: your_api_key"
```

### Download Project Report
```sh
curl -X GET https://yourdomain.com/wp-json/rui/v1/projects/{project_id}/report -H "X-API-Key: your_api_key"
```

### Get Credits Balance
```sh
curl -X GET https://yourdomain.com/wp-json/rui/v1/credits/balance -H "X-API-Key: your_api_key"
```

**Plugin Overview:** The WooCommerce extension/plugin will enable customers to purchase credits for submitting URLs for indexing. Each credit represents one URL submission. The plugin will integrate with WooCommerce, allowing customers to manage their credits and projects within the customer area.

**Key Features and Functionality:**

1.  **Credits Purchase and Management:**
    
    *   **Product Type:** Credits sold as a separate WooCommerce product.
    *   **Pricing:** Fixed price per credit with bulk discounts:
        *   5% discount for 5000 credits and above
        *   10% discount for 10000 credits and above
        *   15% discount for 20000 credits and above
        *   20% discount for 40000 credits and above
        *   30% discount for 100000 credits and above
    *   **Display:** Remaining credits shown as a simple number in the customer area.
    *   **Concurrency and Synchronization:** Implement transactional integrity and locking mechanisms to ensure accurate credit deductions and refunds.
2.  **URL Submission and Project Management:**
    
    *   **Access Control:** Form accessible only to logged-in users.
    *   **Integration:** URL submission form and project management combined into a new menu item/page in the customer area.
    *   **Form Fields:**
        *   Project Name (mandatory)
        *   URL Submission (up to 9999 URLs, each starting with http:// or https://)
        *   Email Notifications Checkbox (optional, defaults to no notifications)
    *   **Validation:** URLs sanitized to ensure they start with http:// or https://.
    *   **Project List:** Lists all projects with their statuses (number of submitted and indexed links, task status) and includes a "Download Report" button.
    *   **Unique Project ID:** Each project will have a unique identifier to avoid conflicts with project names.
    *   **User Interface and Experience:** Follow WooCommerce and WordPress design guidelines for a consistent user experience. Perform user testing to ensure usability.
3.  **Customer Area Integration:**
    
    *   **Credit Display:** Shows remaining credits.
    *   **Buy Credits Button:** Button for purchasing additional credits.
    *   **Projects Section:** Lists all projects with statuses, "Download Report" button, and URL submission form.
4.  **Admin Area Integration:**
    
    *   **Credit Management:** Admins can add/remove credits for customers, with all actions logged.
    *   **Project Viewing:** Admins can view all projects and their statuses in WooCommerce admin sections.
    *   **Logging:** Logs all credit changes (adding/removing, auto refunds) with project name, task ID, and reason.
    *   **API Account Balance Monitoring:**
        *   Checks balance automatically with each link submission.
        *   Sends email and admin notification if balance falls below 100000.
    *   **Customization and Scalability:** Develop the plugin with a modular approach to facilitate future updates and scalability. Use hooks and filters to extend functionality.
5.  **API Integration:**
    
    *   **Account Balance:** Admins can check account balance via the SpeedyIndex API.
    *   **Task Creation:** Creates tasks for URL indexing via the SpeedyIndex API.
    *   **Task Status:** Periodically updates task status via the SpeedyIndex API.
    *   **Error Handling:** Retries failed API requests every 15 minutes using WP cron. After three failures, sends an email and displays admin notification.
    *   **Download Report:** Fetches and parses reports via the SpeedyIndex API. Generates CSV files for download, with URLs in column A and statuses (Indexed/Not Indexed) in column B.
    *   **API Rate Limiting and Performance:** Implement rate limiting and caching for API requests to manage load. Use asynchronous processing for URL submissions.
    *   **API Reliability and Error Handling:** Implement robust error handling and retry logic for API requests. Use WP cron jobs for scheduled retries.
6.  **Auto Refund Mechanism:**
    
    *   **14-Day Status Update:** Updates project status 14 days after creation via the SpeedyIndex API.
    *   **Credit Refund:** Refunds 80% of unindexed links (rounded up) as credits to the customer's account.
    *   **One-Time Action:** Ensures refund occurs only once per project using a meta value.
    *   **Logging:** Logs each auto refund with project name, task ID, and reason (auto refund).
7.  **Notifications:**
    
    *   **Project Status Changes:** Optional notifications for customers on project status changes, enabled by checkbox during project creation.
    *   **API Failure Notifications:** Admins notified of API failures via email and admin notifications.
8.  **Design and Interface:**
    
    *   **User Interface:** Uses standard WooCommerce/WordPress design elements for consistency.
9.  **RESTful API:**
    
    *   **Authentication:** Customers will authenticate using API keys.
    *   **Endpoints:**
        *   **Submit URLs:**
            *   **Endpoint:** POST /api/v1/projects
            *   **Parameters:** project\_name, urls (array of URLs), notify\_on\_status\_change (optional)
            *   **Response:** Confirmation of project creation and unique project ID.
        *   **Get Project Status:**
            *   **Endpoint:** GET /api/v1/projects/{project\_id}
            *   **Response:** Project status, number of submitted and indexed links.
        *   **Download Report:**
            *   **Endpoint:** GET /api/v1/projects/{project\_id}/report
            *   **Response:** CSV file download with URLs and statuses.
        *   **Get Credits Balance:**
            *   **Endpoint:** GET /api/v1/credits/balance
            *   **Response:** Current credit balance.
    *   **Rate Limiting:** Implement rate limiting to prevent abuse of the API.
    *   **Documentation:** Provide detailed API documentation for customers.
    *   **RESTful API Security:** Implement secure authentication methods (API keys, tokens) and rate limiting to prevent abuse. Provide clear API documentation.

**API Endpoints Used:**

1.  **Check Balance:** GET /v2/account
2.  **Create Task:** POST /v2/task/google/indexer/create
3.  **Get Task List:** GET /v2/task/google/indexer/list/<PAGE>
4.  **Get Task Status:** POST /v2/task/google/indexer/status
5.  **Download Task Report:** POST /v2/task/google/indexer/report
6.  **Index Single URL:** POST /v2/google/url

## API Documentation

SpeedyIndex API v2

https://api.speedyindex.com

Check the balance

Task creation

To get the balance, send a GET request

GET /v2/account

Header

Authorization: <API KEY>

Response

balance.indexer - an integer, your balance for google link indexing service  
balance.checker - an integer, your balance for google link indexation check service

Request example

curl -H "Authorization: <API KEY>" https://
api.speedyindex.com/v2/account

Response example

{"code":0,"balance":{"indexer":10014495,"checker":100732}}

To create a task, send a POST request to the /v2/task/google/<TASK TYPE>/create endpoint, passing the task name as the title parameter.

POST /v2/task/google/<TASK TYPE>/create  
TASK TYPE - string, task type. Possible values: indexer, checker

indexer - link indexing  
checker - check indexation of links

Header

Authorization: <API KEY>

Request

title - string, task name (optional)  
urls - array of strings. Links you want to add to the task No more than 10,000 links in a single request

Response

code - number  
\- 0 links successfully added  
\- 1 top up balance  
\- 2 the server is overloaded. In this case, repeat the request later

task\_id - string, identifier of the created task type - string, task type.

Request example

curl -X POST -H 'Authorization: <API KEY>' -H 'Content-Type:
application/json' -d $'{"title":"test title","urls":\["https://
google.com","https://google.ru"\]}' https://api.speedyindex.com/
v2/task/google/indexer/create

Response example

{"code":0,"task\_id":"6609d023a3188540f09fec6c","type":"google/
indexer"}

Getting the list of tasks Getting task status

GET /v2/task/google/<TASK TYPE>/list/<PAGE>  
TASK TYPE - string, task type. Possible values: «indexer», «checker» indexer - link indexing  
checker - check indexation of links

PAGE - page number, each page contains 1000 tasks. Numbering starts from 0. The task list is sorted from new to old.

Header

Authorization: <API KEY>

Response

code - status code,  
page - current page number,  
last\_page - last page number,  
result - An array of tasks is returned in which:

id - string, task identifier  
size - number, total number of references in the task processed\_count - number, number of processed links indexed\_count - number, number of indexed links  
type - string, task type  
title - string, task title  
created\_at - task creation date

Example request

curl -H "Authorization: <API KEY>" https://
api.speedyindex.com/v2/task/google/checker/list/0

Example response

{«code":0,"page":0,"last\_page":0,"result":
\[{"id":"65f8c7315752853b9171860a","size":690,"processed\_cou
nt":690,"indexed\_count":279,"title":"index\_.txt","type":"go
ogle/checker","created\_at":"2024-03-18T22:58:56.901Z"}\]}

To get task status send a POST request

POST /v2/task/google/<TASK TYPE>/status  
TASK TYPE - string, task type. Possible values: «indexer», «checker»

indexer - link indexing  
checker - check indexation of links

Header

Authorization: <API KEY>

Request

task\_id - id, task identifier

Response

id - string, task identifier  
size - number, total number of references in the task processed\_count - number, number of processed links indexed\_count - number, number of indexed links  
type - string, task type  
title - string, task title  
created\_at - task creation date

Example request

curl -X POST -H "Authorization: <API KEY>" -H 'Content-
Type: application/json' -d
$'{"task\_id":"65f8c7305759855b9171860a"}' https://
api.speedyindex.com/v2/task/google/indexer/status

Example response

{"code":0,"result":
{"id":"65f8c7305759855b9171860a","size":690,"processed\_coun
t":690,"indexed\_count":279,"title":"index\_.txt","type":"goo
gle/indexer","created\_at":"2024-03-18T22:58:56.901Z"}}

Downloading a task report Index a single link

To download the full report on the task, including a list of indexed links send a POST request

POST /v2/task/google/<TASK TYPE>/report  
TASK TYPE - string, task type. Possible values: «indexer», «checker"

indexer - link indexing  
checker - check indexation of links

Header

Authorization: <API KEY>

Request

task\_id - id, task identifier

Response

id - string, task identifier  
size - number, total number of references in the task processed\_count - number, number of processed links indexed\_links - array of string, indexed links unindexed\_links - array of string, unindexed links  
type - string, task type  
title - string, task title  
created\_at - task creation date

Example request

curl -X POST -H "Authorization: <API KEY>" -H 'Content-Type:
application/json' -d $'{"task\_id":"653278d8a2b987d72b36f2a2"}'
https://api.speedyindex.com/v2/task/google/indexer/report

Example response

To index a single link send a POST request.

POST /v2/google/url

Header

Authorization: <API KEY>

Request

url - string, link you want to index

Response

code - number  
\- 0 link successfully added  
\- 1 top up balance  
\- 2 the server is overloaded. In this case, repeat the request later

Example request

curl -X POST -H 'Authorization: <API KEY>' -H
'Content-Type: application/json' -d
$'{"url":"https://google.ru"}' https://
api.speedyindex.com/v2/google/url

Example response

{"code":0}

{"code":0,"result":
{"id":"653278d8a2b987d72b36f2a2","size":3,"processed\_count":3,"in
dexed\_links":\["https://google.com","https://google.com","https://
google.com"\],"unindexed\_links":
\[\],"title":"test","created\_at":"2023-10-20T12:55:52.031Z",type:"g
oogle/indexer"}}
  
## Plugins that can optionally be used to complement the indexing plugin:  
  

*   **Stripe Payment Gateway Integration:**
    
    *   **WooCommerce Stripe Payment Gateway:** This plugin allows you to accept payments through Stripe, supporting various payment methods including credit cards, Apple Pay, and Google Pay. It’s easy to set up and configure with your Stripe account.
        *   **Features:**
            *   Secure payment processing.
            *   Supports subscription and recurring payments.
            *   PCI compliance.
            *   Detailed transaction logging.
*   **Credits Management:**
    
    *   **WooCommerce Points and Rewards:** This plugin enables you to sell credits (points) that customers can use within your store. It can be customized to deduct credits for URL submissions.
        *   **Features:**
            *   Points can be awarded for purchases, actions, and activities.
            *   Customers can redeem points for discounts or specific products.
            *   Bulk point adjustments.
            *   Detailed reporting and logging.
*   **Discounts and Bulk Pricing:**
    
    *   **Discount Rules for WooCommerce:** This plugin allows you to create advanced discount rules, such as bulk discounts based on the quantity of credits purchased.
        *   **Features:**
            *   Percentage discounts, fixed amount discounts, and BOGO deals.
            *   Conditional discounts based on cart items, user roles, or purchase history.
            *   Scheduled discounts for promotional periods.
*   **Form Creation and Project Management:**
    
    *   **Gravity Forms:** A powerful form builder that can be used to create the URL submission form and manage project entries. It integrates well with WooCommerce.
        
        *   **Features:**
            *   Drag-and-drop form builder.
            *   Conditional logic for form fields.
            *   Integration with payment gateways for form submissions.
            *   Extensive add-ons for extended functionality.
    *   **WPForms:** Another user-friendly form builder with robust features for creating custom forms and managing submissions.
        
        *   **Features:**
            *   Pre-built form templates.
            *   Conditional logic.
            *   Integration with email marketing services and payment gateways.
            *   User-friendly drag-and-drop interface.
*   **Customer Area Enhancements:**
    
    *   **WooCommerce My Account Customization:** This plugin allows you to customize the WooCommerce My Account area, adding sections for credits balance, project management, and other custom functionalities.
        *   **Features:**
            *   Add custom tabs and endpoints.
            *   Display custom content and user-specific information.
            *   Easy integration with WooCommerce hooks and filters.
*   **Admin Tools for Credit Management and Project Viewing:**
    
    *   **User Role Editor:** Helps manage different admin roles with specific capabilities to manage credits and view projects.
        *   **Features:**
            *   Customizable user roles and permissions.
            *   Assign and modify capabilities for specific roles.
            *   Integration with WooCommerce and other plugins.
*   **API Integration:**
    
    *   **WP All Import:** While primarily used for importing data, this plugin can be customized for handling API requests and data synchronization with external APIs like SpeedyIndex.
        *   **Features:**
            *   Schedule imports and updates.
            *   Map API data to custom fields.
            *   Supports REST API and custom endpoint integrations.
*   **Cron Jobs and Scheduled Tasks:**
    
    *   **WP Crontrol:** This plugin allows you to view and control WP-Cron events, which can be used for scheduling periodic checks for project statuses and performing automated refunds.
        *   **Features:**
            *   Manage and create custom cron schedules.
            *   Monitor existing cron jobs and debug issues.
            *   Schedule custom tasks and actions.
*   **Email Notifications:**
    
    *   **WP Mail SMTP:** Ensures reliable email delivery for project status updates and notifications.
        
        *   **Features:**
            *   Integration with various email services (SMTP, SendGrid, Mailgun).
            *   Email logging for troubleshooting.
            *   Easy configuration with detailed documentation.
    *   **Better Notifications for WP:** Customizable email notifications for various WordPress events, including project status changes and admin alerts.
        
        *   **Features:**
            *   Customizable email templates.
            *   Notifications for specific user roles and events.
            *   Integration with WooCommerce and other plugins.

### File and Code Outline for "Rapid URL Indexer"

#### Plugin Structure

*   **rapid-url-indexer/**
    *   **assets/**
        *   css/
        *   js/
    *   **includes/**
        *   class-rapid-url-indexer-activator.php
        *   class-rapid-url-indexer-deactivator.php
        *   class-rapid-url-indexer-admin.php
        *   class-rapid-url-indexer-customer.php
        *   class-rapid-url-indexer-api.php
        *   class-rapid-url-indexer.php
    *   **languages/**
    *   **templates/**
    *   rapid-url-indexer.php
    *   uninstall.php

#### Plugin Files Description

1.  **Main Plugin File (rapid-url-indexer.php):**
    *   Plugin header
    *   Activation/Deactivation hooks
    *   Include necessary files
    *   Initialize the plugin

<?php /\*\* \* Plugin Name: Rapid URL Indexer \* Description: WooCommerce extension for purchasing and managing URL indexing credits. \* Version: 1.0.0 \* Author: Your Name \* Text Domain: rapid-url-indexer \*/ // Exit if accessed directly if (!defined('ABSPATH')) exit; // Include required files require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer-activator.php'; require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer-deactivator.php'; require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer-admin.php'; require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer-customer.php'; require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer-api.php'; require\_once plugin\_dir\_path(\_\_FILE\_\_) . 'includes/class-rapid-url-indexer.php'; // Activation/Deactivation hooks register\_activation\_hook(\_\_FILE\_\_, array('Rapid\_URL\_Indexer\_Activator', 'activate')); register\_deactivation\_hook(\_\_FILE\_\_, array('Rapid\_URL\_Indexer\_Deactivator', 'deactivate')); // Initialize the plugin add\_action('plugins\_loaded', array('Rapid\_URL\_Indexer', 'init'));

1.  **Activator Class (class-rapid-url-indexer-activator.php):**
    
    *   Handles actions on plugin activation (e.g., creating necessary database tables).
2.  **Deactivator Class (class-rapid-url-indexer-deactivator.php):**
    
    *   Handles actions on plugin deactivation.
3.  **Admin Class (class-rapid-url-indexer-admin.php):**
    
    *   Admin interface for managing credits and viewing projects.
    *   Add new menu items in WooCommerce admin.
    *   Handle admin AJAX requests.
4.  **Customer Class (class-rapid-url-indexer-customer.php):**
    
    *   Customer interface for viewing credits, submitting URLs, and managing projects.
    *   Add new menu items in the customer area.
    *   Handle customer AJAX requests.
5.  **API Class (class-rapid-url-indexer-api.php):**
    
    *   Handle API requests and responses.
    *   Integrate with SpeedyIndex API for creating tasks, checking status, and downloading reports.
    *   Implement rate limiting and error handling.
6.  **Main Plugin Class (class-rapid-url-indexer.php):**
    
    *   Central class to initialize and manage the plugin components.
7.  **Uninstall Script (uninstall.php):**
    
    *   Clean up database entries and other resources on plugin uninstallation.

#### Includes

*   **Assets** (CSS and JS files for styling and functionality):
    *   assets/css/admin.css - Styles for admin interface.
    *   assets/css/customer.css - Styles for customer interface.
    *   assets/js/admin.js - Scripts for admin functionality.
    *   assets/js/customer.js - Scripts for customer functionality.

#### Database Structure

*   **Database Tables:**
    *   wp\_rapid\_url\_indexer\_credits - Track customer credits.
    *   wp\_rapid\_url\_indexer\_projects - Store project details.
    *   wp\_rapid\_url\_indexer\_logs - Log credit changes, API errors, and other actions.

#### Functions and Hooks

1.  **Credits Management:**
    
    *   Add credits as WooCommerce products.
    *   Display remaining credits in the customer area.
    *   Handle credit deductions and refunds.
2.  **URL Submission and Project Management:**
    
    *   Validate and sanitize submitted URLs.
    *   Store project details in the database.
    *   List projects with status and download report functionality.
3.  **API Integration:**
    
    *   Create tasks and check status via SpeedyIndex API.
    *   Handle API errors and implement retry logic with WP Cron.
4.  **Notifications:**
    
    *   Email notifications for project status changes and API failures.
    *   Admin notifications for low balance and critical errors.
5.  **Auto Refund Mechanism:**
    
    *   Periodic status updates and auto refunds for unindexed URLs.
6.  **Admin and Customer Interfaces:**
    
    *   Custom WooCommerce admin and customer menu items.
    *   Forms for URL submission and project management.
7.  **RESTful API:**
    
    *   Endpoints for submitting URLs, checking project status, downloading reports, and checking credit balance.
    *   Secure authentication and rate limiting.
