<?php

# Class to create a labs inventory system
require_once ('frontControllerApplication.php');
class labsInventory extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'database'				=> 'labs',
			'table'					=> 'equipment',
			'administrators'		=> 'administrators',
			'imageGenerationStub'	=> '/images/generator',			# Location of the image generation script, from web root
			'imageDirectory'		=> '/images/',					# Location of the images directory from the application root
			'thumbnailWidth'		=> 140,							# Image width
			'imageWidth'			=> 300,							# Image width
			'page404'				=> 'sitetech/404.html',			# Location of 404 page
			'authentication'		=> true,
			'div'					=> 'labsinventory',
			#!# Ideally the administrators page would have tickboxes for who receives the e-mails, instead of setting recipient externally
			'recipient'				=> NULL,
			'labManagerNames'		=> NULL,
			'userCallback'			=> NULL,
			'introductoryText'		=> false,
			'tabUlClass'			=> 'tabsflat',
			
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'equipment' => array (
				'description' => 'View equipment',
				'url' => 'equipment/',
				'tab' => 'Equipment',
				'icon' => 'wrench_orange',
			),
			'article' => array (
				'description' => 'Item: %detail',
				'usetab' => 'equipment',
			),
			'book' => array (
				'description' => 'Book item: %detail',
				'usetab' => 'equipment',
			),
			'search' => array (
				'description' => 'Search',
				'tab' => 'Search',
				'url' => 'search/',
				'icon' => 'magnifier',
			),
			'basket' => array (
				'description' => 'Basket',
				'tab' => 'Basket',
				'url' => 'basket/',
				'icon' => 'basket',
			),
			'checkout' => array (
				'description' => 'Checkout',
				'usetab' => 'basket',
				'url' => 'checkout/',
			),
			'orders' => array (
				'description' => false,	// So that we can specify this manually in the function itself, though unfortunately this disables the tab tooltip
				'tab' => 'Orders',
				'url' => 'orders/',
				'icon' => 'application_double',
				'administrator' => true,
			),
			'message' => array (
				'description' => false,	// So that we can specify this manually in the function itself, though unfortunately this disables the tab tooltip
				'usetab' => 'review',
				'administrator' => true,
			),
			'external' => array (
				'description' => 'External users and certain others',
				'usetab' => 'Home',
				'url' => 'external/',
			),
		);
		
		# Get the user data from the callback
		// Only these fields are used: 'username', 'name', 'telephone', 'email', 'staffType__JOIN__people__staffType__reserved', isStaff, isGraduate, staffType
		$this->userData = $this->getUser ($this->user);
		
		# Return the actions
		return $actions;
	}
	
	
	# Status labels, in order that they should be listed on the review screen
	# IMPORTANT: If changing this, the database structure ENUM must also be updated in shoppingcartOrders.status
	private $statusLabels = array (
		'unfinalised'	=> 'Unfinalised',
		'finalised'		=> 'Finalised',
		'shipped'		=> 'Loaned',
		'returned'		=> 'Returned',
		'lost'			=> 'Lost in field',
		'ignore'		=> 'Ignored',
	);
	
	
	
	# Additional default processing
	protected function main ()
	{
		# Start a cookie session
		if (!session_id ()) {	// If not already started, e.g. in an embedded context
			session_start ();
		}
		
		# Set the image directory
		$this->settings['imageDirectory'] = $this->baseUrl . $this->settings['imageDirectory'];
		
		# Get the equipment types
		$this->equipmentTypes = $this->getEquipmentTypes ();
		
		# Get the sundries
		$sundries = $this->getSundries ();
		
		# Define the disclaimer text
		$utf8PoundSign = chr(0xc2).chr(0xa3);	// See http://www.tachyonsoft.com/uc0000.htm#U00A3
		$this->disclaimerText = "All the equipment borrowed becomes the responsibility of the person who has signed for it. He/she/they will be held financially responsible for any loss howsoever caused, including the costs of repair, replacement or any uninsured loss, excess charge (typically {$utf8PoundSign}1,000 for University insurance), import duty or customs charge.";
		
		# Load the shopping cart library with the specified settings
		$shoppingCartSettings = array (
			'name'				=> $this->settings['applicationName'],
			'provider'			=> __CLASS__,
			'database'			=> $this->settings['database'],		// Shop database
			'administrators'	=> $this->administrators,
			'disclaimerText'	=> $this->disclaimerText . ' You must type YES to accept.',
			'pricePrefix'		=> 'Replacement value:',
			'sundries'			=> $sundries,
			'statusLabels'		=> $this->statusLabels,
			'dateLimitations'	=> true,
			'requireUser'		=> true,
			'confirmationEmail'	=> false,		// Handled internally in the present class instead by checkout() then confirmationEmail()
		);
		require_once ('shoppingCart.php');
		$this->shoppingCart = new shoppingCart ($this->databaseConnection, $this->baseUrl, $shoppingCartSettings, $this->userData, $this->userIsAdministrator, $this->user);
	}
	
	
	# Function to get the user
	private function getUser ($userId)
	{
		# Get the data from the callback and return it
		$callbackFunction = $this->settings['userCallback'];
		$data = $callbackFunction ($userId);
		return $data;
	}
	
	
	# Administrators
	public function administrators ($null = NULL, $boxClass = 'graybox', $showFields = array ('active' => 'Active?', 'email' => 'E-mail', 'privilege' => 'privilege', 'name' => 'name', 'forename' => 'forename', 'surname' => 'surname', ))
	{
		# Use the standard page but add on a message
		#!# Need to energineer out this manual requirement
		echo "\n<div class=\"box\">\n\t<p class=\"warning\"><strong>Important: if adding/removing administrators, make sure you contact the Webmaster so that the " . htmlspecialchars ($this->settings['recipient']) . " e-mail address is manually updated.</strong></p>\n</div>";
		echo parent::administrators ();
	}
	
	
	# Welcome screen
	public function home ()
	{
		$html = '';
		
		# Show the introduction
		$html .= "\n" . "<p><strong>Welcome</strong> to the {$this->settings['applicationName']}.</p>";
		if ($this->settings['introductoryText']) {
			$html .= "\n<p>" . htmlspecialchars ($this->settings['introductoryText']) . '</p>';
		}
		$html .= "\n" . "<p><a href=\"{$this->baseUrl}/external/\">External users and certain others</a> should fill in a loan form.</p>";
		
		# Listings
		$html .= "\n<h2>Equipment categories</h2>";
		$html .= $this->equipmentTypesList ($this->equipmentTypes);
		
		# Search
		$html .= "\n<h2>Search the collection</h2>";
		$html .= "\n<p>Alternatively, you can search the inventory:</p>";
		echo $html;
		$this->search ();
	}
	
	
	# Equipment category/item listing screen
	protected function equipment ($item)
	{
		# Start the HTML
		$html  = '';
		
		# Get the data or end
		if (!$this->equipmentTypes) {
			echo $html = '<p>Error: no equipment types were found.</p>';
			return false;
		}
		
		# Determine if a type is specified, and show the list of categories if so
		if (!$equipmentType = ($item && isSet ($this->equipmentTypes[$item]) ? $item : false)) {
			
			# Redirect if there is already a type set from the cookie
			if (isSet ($_SESSION['type']) && isSet ($this->equipmentTypes[$_SESSION['type']])) {
				require_once ('application.php');
				$location = $_SERVER['_SITE_URL'] . $this->baseUrl . '/equipment/' . $_SESSION['type'] . '.html';
				unset ($_SESSION['type']);
				application::sendHeader (302, $location);
			}
			
			# Create a list
			$html  = $this->equipmentTypesList ($this->equipmentTypes);
			
			# Show the HTML
			echo $html;
			
			# End
			return true;
		}
		
		# Create a url => name version of the equipment types list
		$equipmentTypesLinks = array ("{$this->baseUrl}/equipment/" => 'Select equipment type');
		foreach ($this->equipmentTypes as $urlSlug => $type) {
			$url = "{$this->baseUrl}/equipment/{$urlSlug}.html";
			$text = $type['equipmentType'] . " ({$type['total']})";
			$equipmentTypesLinks[$url] = $text;
		}
		
		# Show a droplist for changing categories easily
		$selected = "{$this->baseUrl}/equipment/{$equipmentType}.html";
		$html .= application::htmlJumplist ($equipmentTypesLinks, $selected, $action = '', $name = 'jumplist', $parentTabLevel = 0, $class = 'jumplist', $introductoryText = 'Type:');
		
		# Show the title
		$html .= "\n<p>Select the item from the <strong>" . htmlspecialchars ($this->equipmentTypes[$equipmentType]['equipmentType']) . "</strong> category:</p>";
		
		# Get the equipment for this type
		if (!$equipment = $this->getArticlesData ($equipmentType)) {
			echo $html = '<p>No equipment was found for this category.</p>';
			return false;
		}
		
		# Convert to a table
		$html .= $this->articlesTable ($equipment);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create an equipment types list
	private function equipmentTypesList ($equipmentTypes)
	{
		# Create a list
		$list = array ();
		foreach ($equipmentTypes as $urlSlug => $equipment) {
			$list[$urlSlug] = "<a href=\"{$this->baseUrl}/equipment/{$urlSlug}.html\"><strong>" . htmlspecialchars ($equipment['equipmentType']) . "</strong></a> (total: {$equipment['total']})";
		}
		
		# Compile the HTML
		$html  = "\n<p>Please firstly select the type of equipment:</p>";
		$html .= application::htmlUl ($list, 0, 'spaced');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show a table of articles
	private function articlesTable ($equipment)
	{
		# Return empty string if no articles
		if (!$equipment) {return '';}
		
		# Organise the data
		$table = array ();
		foreach ($equipment as $id => $item) {
			$name = htmlspecialchars ($item['name']);
			list ($imageHtml, $thumbnailLocation) = $this->imageHtml ($id, $this->settings['thumbnailWidth'], "thumbnails/", $name);
			$link = "{$this->baseUrl}/equipment/{$id}/";
			$table[$id] = array (
				''	=> "<a href=\"{$link}\">{$imageHtml}</a>",
				'Name and manufacturer' => "<p class=\"name\"><strong><a href=\"{$link}\">{$name}</a></strong></p>\n<p class=\"small\">" . htmlspecialchars ($item['manufacturer']) . '</p>',
			);
		}
		
		# Show as a table
		$html = application::htmlTable ($table, $tableHeadingSubstitutions = array (), 'equipmentlist regulated lines', false, false, $allowHtml = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to generate a thumbnail if not already present
	private function imageHtml ($id, $asThumbnail = false, $thumbnailsSubfolder = 'thumbnails/', $alt = false, $failureHtml = '<div class="thumbnail">No image available</div>', $class = 'thumbnail')
	{
		# Set the failure mode as two items
		#!# This is very hacky and indicates the need for refactoring
		$failureHtml = array ($failureHtml, $thumbnailLocation = '');
		
		# Ensure the image directory exists
		$imageDirectory = $_SERVER['DOCUMENT_ROOT'] . $this->settings['imageDirectory'];
		if (!is_dir ($imageDirectory)) {return $failureHtml;}
		
		# Ensure the thumbnails directory exists (even if on this occasion we are not showing it), or if not, attempt to create it
		if (!is_dir ($imageDirectory . $thumbnailsSubfolder)) {
			if (!is_writable ($imageDirectory)) {
				return $failureHtml;
			} else {
				umask (0);
				mkdir ($imageDirectory . $thumbnailsSubfolder, 0775);
			}
		}
		
		# Determine the URL and filename of the file
		$imageLocation = $this->settings['imageDirectory'] . $id . '.jpg';
		$imageFile = $_SERVER['DOCUMENT_ROOT'] . $imageLocation;
		
		# Determine the URL and filename of the thumbnail (which may not yet exist)
		$thumbnailLocation = $this->settings['imageDirectory'] . $thumbnailsSubfolder . $id . '.jpg';
		$thumbnailFile = $_SERVER['DOCUMENT_ROOT'] . $thumbnailLocation;
		
		# Return an empty string if the image file is not readable, as there is no point trying to link to it or show it online
		if (!is_readable ($imageFile)) {return $failureHtml;}
		
		# Generate the thumbnail if not already present or it is stale
		if (is_readable ($imageFile)) {
			
			# Determine whether to create a thumbnail
			$createThumbnail = false;
			if (is_readable ($thumbnailFile)) {
				if (filemtime ($imageFile) > filemtime ($thumbnailFile)) {
					$createThumbnail = true;
				}
			} else {
				$createThumbnail = true;
			}
			
			# Create the thumbnail if required
			if ($createThumbnail) {
				require_once ('image.php');
				if (!image::resize ($imageFile, $outputFormat = 'jpg', $newWidth = $asThumbnail, $newHeight = '', $thumbnailFile)) {
					return $failureHtml;
				}
			}
		}
		
		# Construct the image HTML
		$image = ($asThumbnail ? $thumbnailLocation : $imageLocation);
		list ($width, $height, $type, $attributes) = getimagesize ($_SERVER['DOCUMENT_ROOT'] . $image);
		$alt = htmlspecialchars ($alt);
		$imageHtml = "<img src=\"{$image}\" alt=\"{alt}\" class=\"{$class}\" {$attributes}>";
		
		# Return the image location (irrespective of whether the thumbnail is actually present - the URL should still be the same)
		return array ($imageHtml, $thumbnailLocation);
	}
	
	
	# Central function to get a list/item of equipment
	private function getArticlesData ($equipmentTypeUrlSlug = false, $equipmentId = false, $restrictionSql = false)
	{
		# Get the data or end
		$query = "SELECT
			equipment.*,
			DATE_FORMAT(CAST(equipment.dateAcquired AS DATE), '%D %M, %Y') AS dateAcquired,
			equipmentType.equipmentType,
			equipmentType.urlSlug,
			locations.location
		FROM equipment
		LEFT JOIN equipmentType ON type__JOIN__labs__equipmentType__reserved = equipmentType.id
		LEFT JOIN locations ON location__JOIN__labs__locations__reserved = locations.id
		WHERE
			visibleOnline = 'Y'"
			. ($equipmentTypeUrlSlug ? " AND urlSlug = " . $this->databaseConnection->quote ($equipmentTypeUrlSlug) : '')
			. ($equipmentId ? " AND equipment.id = " . $this->databaseConnection->quote ($equipmentId) : '')
			. $restrictionSql
		 . "ORDER BY name,manufacturer,id;";
		$function = ($equipmentId ? 'getOne' : 'getData');
		if (!$data = $this->databaseConnection->$function ($query, 'labs.equipment')) {return false;}
		
		# Remove specific fields
		unset ($data['type__JOIN__labs__equipmentType__reserved']);
		unset ($data['location__JOIN__labs__locations__reserved']);
		
		# Return the data
		return $data;
	}
	
	
	# Function to get a list of equipment types
	private function getEquipmentTypes ()
	{
		# Get the data or end; this is a join of equipment and equipmentType to get the totals
		$query = "SELECT
			DISTINCT equipmentType,
			equipmentType.urlSlug,
			COUNT(equipment.id) AS total
		FROM equipment
		LEFT JOIN equipmentType ON type__JOIN__labs__equipmentType__reserved = equipmentType.id
		LEFT JOIN locations ON location__JOIN__labs__locations__reserved = locations.id
		WHERE
			visibleOnline = 'Y'
			AND equipmentType IS NOT NULL
		GROUP BY equipmentType, /* Needed for MySQL 5.7: */ equipmentType.urlSlug
		HAVING total > 0
		ORDER BY equipmentType
		;";
		if (!$data = $this->databaseConnection->getData ($query)) {
			application::dumpData ($this->databaseConnection->error ());
			return false;
		}
		
		# Rearrange it as urlSlug => equipment
		$equipmentTypes = array ();
		foreach ($data as $index => $equipmentType) {
			$key = $equipmentType['urlSlug'];
			$equipmentTypes[$key] = $equipmentType;
		}
		
		# Return the data
		return $equipmentTypes;
	}
	
	
	# Function to get a list of sundries
	private function getSundries ()
	{
		# Get the data or end; this is a join of equipment and equipmentType to get the totals
		$query = "SELECT * FROM sundries ORDER BY name;";
		if (!$data = $this->databaseConnection->getPairs ($query)) {return false;}
		
		# Sort in order to remove associative key numbers
		sort ($data);
		
		# Return the data
		return $data;
	}
	
	
	# Article page
	protected function article ($articleId)
	{
		# Get the article data or end
		if (!ctype_digit ($articleId) || !$data = $this->getArticlesData (false, $articleId)) {
			application::sendHeader (404);
			include ($this->settings['page404']);
			return false;
		}
		
		# Start the HTML
		$html  = '';
		
		# Remember this type of equipment
		$_SESSION['type'] = $data['urlSlug'];
		
		# Create the article screen
		$html .= $this->showArticle ($data, $showBookingLinks = false);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to show an article
	private function showArticle ($data, $showBookingLinks = false)
	{
		# Start the HTML
		$html  = '';
		
		# Make the equipmentType field clickable
		$data['equipmentType'] = "<a href=\"{$this->baseUrl}/equipment/{$data['urlSlug']}.html\">{$data['equipmentType']}</a>";
		unset ($data['urlSlug']);
		
		# Add other changes
		if ($data['replacementValue'] == '0.00') {$data['replacementValue'] = 0;}
		
		# Get the field name conversions
		$headings  = $this->databaseConnection->getHeadings ($this->settings['database'], 'equipment');
		$headings += $this->databaseConnection->getHeadings ($this->settings['database'], 'equipmentType');
		
		# Cache the image HTML
		list ($imageHtml, $thumbnailLocation) = $this->imageHtml ($data['id'], $this->settings['imageWidth'], 'small/', $data['name']);
		
		# Remove data that should not be visible
		unset ($data['photograph'], $data['location'], $data['visibleOnline']);
		
		# Add image to the table
		$data = array ('' => "<div class=\"imagelarge\">" . $imageHtml . "\n</div>") + $data;
		
		# Add the shopping cart buttons
		$stockAvailable = ((strlen ($data['stockAvailable']) && $data['stockAvailable']) || !strlen ($data['stockAvailable']));	// Stock available unless a number '0' has been entered
		$isLoanable = ($stockAvailable && $this->isLoanable ($data['loanable'], $this->userData));
		unset ($data['stockAvailable']);
		if ($isLoanable) {
			$requestUriAuthorised = "{$this->baseUrl}/equipment/{$data['id']}/";
			$price = $data['replacementValue'];
			$bookItemLink = $this->shoppingCart->controls ($data['id'], $requestUriAuthorised, $title = $data['name'], $price, $vat = NULL, $thumbnailLocation, $maximumAvailable = $data['quantity']);
			$html .= "\n<p>{$bookItemLink}</p>";
			$data['loanable'] = "Yes: {$bookItemLink}";
		} else {
			$html  = "\n\t<ul class=\"nobullet actions noprint\">";
			if ($stockAvailable) {
				$html .= "\n\t\t<li><span class=\"actions\">(Not loanable" . ($isLoanable === false ? ' to you' : '') . ")</span></li>";
			} else {
				$html .= "\n\t\t<li><span class=\"actions\">(Temporarily unavailable)</span></li>";
			}
			$html .= "\n\t</ul>";
		}
		
		# Amend presentation
		$data['replacementValue'] = $this->priceDisplay ($data['replacementValue']);
		
		# Continue with the record
		$html .= "\n" . '<h2>' . str_replace ('%detail', htmlspecialchars ($data['name']), $this->actions[$this->action]['description']) . "</h2>";
		$html .= "\n" . '<div class="article">';
		$html .= "\n" . application::htmlTableKeyed ($data, $headings, false /*'<em class="comment">(Unknown)</em>' */, 'lines regulated', $allowHtml = true);
		$html .= "\n" . '</div>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if an item is loanable
	private function isLoanable ($itemSetting, $userData)
	{
		switch ($itemSetting) {
			case 'No':	// Fall-through
			case 'Unknown':
				return NULL;	// Not loanable at all
				
			case 'Yes - staff only':
				return ($userData['isStaff']);
				
			case 'Yes - staff/graduates only':
				return ($userData['isStaff'] || $userData['isGraduate']);
				
			case 'Yes':
				return ($this->user);	// Anyone logged in - i.e. people in the Department of any status, and others with a CRSID
				
			default:
				return false;
				#!# Throw error - should never get this far
		}
	}
	
	
	# Function to provide a search facility
	protected function search ()
	{
		# Determine any default
		$default = (isSet ($_GET['item']) ? $_GET['item'] : false);
		
		# Create a new form
		$form = new form (array (
			'display' => 'template',
			'displayTemplate' => '{search} {[[PROBLEMS]]} {[[SUBMIT]]}<br />{wildcard}',
			'requiredFieldIndicator' => false,
			'submitButtonText' => 'Search!',
			'formCompleteText' => false,
			'reappear' => true,
			'escapeOutput' => true,
			'submitTo' => "{$this->baseUrl}/search/",
		));
		$form->input (array (
			'name'		=> 'search',
			'title'		=> 'Search',
			'required'	=> true,
			'default' 	=> $default,
		));
		$form->checkboxes (array (
			'name'		=> 'wildcard',
			'title'		=> 'Allow partial name searching',
			'values'	=> array ('wildcard' => 'Allow partial name searching'),
			'default'	=> array ('wildcard'),
		));
		if (!$result = $form->process ()) {return false;}
		
		# Define the search sub-SQL
		$searchSql = ($result['wildcard']['wildcard'] ? $result['search'] : "[[:<:]]{$result['search']}[[:>:]]");
		
		# Define the restriction, surrounding the search term with a word-boundary limitation
		$restrictionSql = "
			AND (
				   name REGEXP '{$searchSql}'
				OR model REGEXP '{$searchSql}'
				OR manufacturer = '{$searchSql}'
				OR serialNumber = '{$result['search']}'
				OR notesPublic REGEXP '{$searchSql}'
			)
		";
		
		# Get the data or end
		if (!$data = $this->getArticlesData (false, false, $restrictionSql)) {
			echo $html = '<p>No items were found.</p>';
			return false;
		}
		
		# Compile the HTML
		$total = count ($data);
		$html  = "\n<p>" . ($total == 1 ? 'One article was found:' : "{$total} articles were found:") . '</p>';
		$html .= $this->articlesTable ($data);
		
		# Echo the HTML
		echo $html;
	}
	
	
	# Function to provide the shop basket
	public function basket ()
	{
		# Delegate to the shopping cart system
		echo $this->shoppingCart->basket ();
	}
	
	
	# Function to provide the shop checkout
	public function checkout ()
	{
		# Delegate to the shopping cart system
		list ($result, $html) = $this->shoppingCart->checkout ();
		
		# End if no result
		if (!$result) {
			echo $html;
			return false;
		}
		
		# Send and show a confirmation e-mail
		$html = $this->confirmationEmail ($result);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to list the orders
	public function orders ($id = false)
	{
		# Determine if in final confirmation mode
		$confirmationMode = (isSet ($_GET['mode']) && ($_GET['mode'] == 'confirmation'));
		
		# Delegate to the shopping cart system
		list ($result, $html, $isUpdatedFinalised) = $this->shoppingCart->orders ($id, $confirmationMode, $localCollectionModeEnabled = true, $messageLink = true, $listingFields = array ('id', 'name', 'startDate', 'endDate', 'status', ));
		
		# End if no result
		if (!$result) {
			echo $html;
			return false;
		}
		
		# Send and show a confirmation e-mail
		$html = $this->confirmationEmail ($result, true, $isUpdatedFinalised);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to modify the display of price
	private function priceDisplay ($value, $addSpaceAfterPoundSign = false)
	{
		# Either set to ? or a formatted string with commas in
		if ($value == '0.00' || $value == '0' || !strlen ($value)) {
			$string = '?';
		} else {
			$string = number_format ($value, 2, '.', ',');
		}
		
		# Add on the pound sign
		$utf8PoundSign = chr(0xc2).chr(0xa3);	// See http://www.tachyonsoft.com/uc0000.htm#U00A3
		$string = $utf8PoundSign . ($addSpaceAfterPoundSign ? ' ' : '') . $string;
		
		# Return the string
		return $string;
	}
	
	
	# Function to send and show a confirmation e-mail
	private function confirmationEmail ($result, $outcomeMode = false, $isUpdatedFinalised = false)
	{
		# Turn the list of items into a formatted order listing
		$totalItems = count ($result['order']);
		$totalAmount = 0;
		$totalAmountsUnknown = 0;
		$items  = "Item groups ({$totalItems}):\n";
		foreach ($result['order'] as $key => $item) {
			$items .= "\nItem:      {$item['name']}";
			$items .= "\nQuantity:  {$item['total']}";
			$items .= "\nValue:     " . $this->priceDisplay ($item['price'], true) . " (replacement value, each)";
			$items .= "\n{$_SERVER['_SITE_URL']}{$item['url']}";
			$items .= "\n";
			
			# Tally the total amount
			if ($item['price'] == '0.00' || $item['price'] == '0' || !strlen ($item['price'])) {
				$totalAmountsUnknown += (int) $item['total'];
			} else {
				$totalAmount += ((int) $item['total'] * (int) $item['price']);
			}
		}
		
		# Describe the total
		$descriptions = array ();
		if ($totalAmount) {
			$descriptions[] = $this->priceDisplay ($totalAmount);
		}
		if ($totalAmountsUnknown) {
			$descriptions[] = ($totalAmountsUnknown == 1 ? 'one item of unknown value' : "{$totalAmountsUnknown} items of unknown value");
		}
		$total = ucfirst (implode (', plus ', $descriptions));	// 'plus' will only get added if there is more than one
		
		# Format the dates
		$dates = date ('l jS F Y', strtotime ($result['startDate'])) . ' - ' . date ('l jS F Y', strtotime ($result['endDate']));
		
		# Determine the opening message
		if ($outcomeMode) {
			$openingMessage  = '';
			if ($isUpdatedFinalised) {$openingMessage .= "[This is an UPDATED RESPONSE. Please check below for any new details.]\n\n\n";}
			$openingMessage .= "Your loan request has been approved as below. Please take particular note of the following comments and collection details.\n\nPlease print out this e-mail and bring it at the time of collection. The loan cannot be completed without a paper copy of these details.";
			$result['comments'] = "\n\nComments from Laboratory staff:\n" . ($result['comments'] ? $result['comments'] : '[No comments added]');
			$result['collectionDetails'] = "\n\nCollection details: \n" . $result['collectionDetails'];
			$result['sundries']= "\n\nSundries:           \n" . ($result['sundries'] ? $result['sundries'] : '[None requested]');
			$userData = $this->getUser ($result['username']);
			$result['status'] = $userData['staffType'];
			$subject = 'loan request: approved' . ($isUpdatedFinalised ? ' [UPDATE]' : '');
		} else {
			$openingMessage = "A request has been submitted as follows.\n\nYou now need to review/approve it at:\n{$_SERVER['_SITE_URL']}{$this->baseUrl}/orders/{$result['id']}/";
			$result['comments'] = '';
			$result['collectionDetails'] = '';
			$result['status'] = $this->userData['staffType'];
			$subject = 'loan request';
		}
		
		# Construct an e-mail for the user to print off and bring in person
		$message = "

{$openingMessage}


Name:         {$result['name']}
E-mail:       {$result['email']}
Telephone:    {$result['telephone']}
Status:       {$result['status']}

Date(s):      {$dates}

Total value:  {$total}
{$result['comments']}{$result['collectionDetails']}{$result['sundries']}


{$items}


[END]
";
		if ($outcomeMode) {
			$message .= "

{$this->disclaimerText}


Signatures:

I AGREE TO THE ABOVE CONDITIONS.
EQUIPMENT RECEIVED IN GOOD ORDER (Signature):



___________________________
{$result['name']}


LOAN APPROVED. (Signature of staff: )



___________________________
Signature of staff ({$this->settings['labManagerNames']})
";
		}
		
		# Prepare message parameters
		$to = ($outcomeMode ? "{$result['name']} <{$result['email']}>" : $this->settings['recipient']);
		$replyTo = ($outcomeMode ? $this->settings['recipient'] : "{$result['name']} <{$result['email']}>");
		$subject = strip_tags ($this->settings['h1']) . ": {$subject} ({$result['name']})";
		$message = wordwrap ($message);
		$from = "Webmaster <{$this->settings['webmaster']}>";
		$extraHeaders  = "From: {$from}";
		if ($outcomeMode) {
			$extraHeaders .= "\r\nCc: {$this->settings['recipient']}";
		}
		$extraHeaders .= "\r\nReply-To: {$replyTo}";
		
		# Send the message
		require_once ('application.php');
		application::utf8Mail ($to, $subject, $message, $extraHeaders);
		
		# Confirm sending
		if ($outcomeMode) {
			$html = application::showMail ($to, $subject, $message, $extraHeaders);
		} else {
			$confirmationMessage  = "<p><img src=\"/images/icons/tick.png\" class=\"icon\" alt=\"\" /> Thank you; your application has been submitted. Staff will review it and get back to you.</p>";
			$confirmationMessage .= "<p><img src=\"/images/icons/error.png\" class=\"icon\" alt=\"\" /> Note that this application does <strong>not</strong> guarantee availability. We will get in touch with you to confirm this.</p>";
			$html = $confirmationMessage;
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to enable messaging of a group of users
	protected function message ($group)
	{
		# End if the page has been sent (via a header refresh)
		if (isSet ($_GET['sent'])) {
			echo "\n<p><img src=\"/images/icons/tick.png\" class=\"icon\" /><strong> The message has been sent.</strong></p>";
			return false;
		}
		
		# Start with a heading
		echo "\n<h2>Message group</h2>";
		
		# Ensure the group is valid
		if (!isSet ($group, $this->statusLabels)) {
			echo $html = "\n<p>There is no such group to message. Please check the URL and try again.</p>";
			return false;
		}
		
		# Get the people in the group
		$query = "SELECT DISTINCT email FROM {$this->settings['database']}.shoppingcartOrders WHERE status = '{$group}';";
		if (!$recipients = $this->databaseConnection->getPairs ($query)) {
			echo $html = "\n<p>There is currently no-one whose order status is <em>{$this->statusLabels[$group]}</em>.</p>";
			return false;
		}
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'developmentEnvironment' => ini_get ('display_errors'),
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'databaseConnection' => $this->databaseConnection,
			'display' => 'paragraphs',
		));
		
		# Introduction
		$form->heading ('', "Use this form to send a message to those with <strong>{$this->statusLabels[$group]} orders</strong>. Use this facility with care.</p>");
		
		# Message composition
		$totalRecipients = count ($recipients);
		$form->input (array (
			'name'		=> 'to',
			'title'		=> 'To',
			'required'	=> true,
			'editable'	=> false,
			'default'	=> ($totalRecipients == 1 ? 'One recipient' : "{$totalRecipients} recipients") . " with order status: '{$this->statusLabels[$group]} orders'",
			'discard'	=> true,
		));
		$form->input (array (
			'name'		=> 'subject',
			'title'		=> 'Subject',
			'required'	=> true,
			'default'	=> $this->settings['applicationName'],
			'size'		=> 60,
		));
		$form->textarea (array (
			'name'		=> 'message',
			'title'		=> 'Message',
			'required'	=> true,
			'cols'		=> 60,
			'rows'		=> 10,
			'default'	=> "Dear user of the {$this->settings['applicationName']},\n\n....\n\n\nBest wishes,\n{$this->userName}",
		));
		$form->input (array (
			'name'		=> 'from',
			'title'		=> 'From',
			'required'	=> true,
			'editable'	=> false,
			'default'	=> "\"{$this->userName}\" <{$this->userEmail}>",
		));
		
		# Process the form
		if (!$result = $form->process ()) {return;}
		
		# Set who the message is to and from
		$to = '';
		$headers  = "From: {$this->settings['administratorEmail']}\r\n";
		$headers .= "Reply-To: {$this->userEmail}\r\n";
		
		# Prepare the recipient list to be used for Bcc:
		$recipients = implode (', ', $recipients);
		$headers .= 'Bcc: ' . $recipients . "\r\n";
		
		# Send the mail and confirm success status
		if (!application::utf8Mail ($to, $result['subject'], $result['message'], $headers)) {
			echo "<p class=\"warning\"><strong>There was a problem sending the mailout.</strong> Please contact the Webmaster before re-attempting to send the message.</p>";
			#!# Need to mail the admin the list upon failure
			return false;
		}
		echo "<p><strong>The e-mail has been successfully sent.</strong> Total individual recipients: " . count ($recipients) . '.</p>';
		
		# Refresh to the current page to avoid an accidental resending due to refresh
		application::sendHeader (301, $_SERVER['_PAGE_URL'] . '?sent');
	}
	
	
	# Data function
	public function data ()
	{
		# Delegate to shopping cart
		return $this->shoppingCart->data ();
	}
	
	
	# External users
	public function external ()
	{
		#!# These forms need to be managed by the system, rather than added independently but linked to
		echo '
			<p>If you are not a member of the University or have been specifically directed to use this form:</p>
			<p>For loan of equipment, please submit a loans form using one of the links below:</p>
			<ul>
				<li><a href="loanform.docx">Loan Application Form</a></li>
				<li><a href="loanform.pdf">Loan Application Form</a></li>
			</ul>';
	}
}

?>
