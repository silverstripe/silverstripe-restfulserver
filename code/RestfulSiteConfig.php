<?php
/**
 * Adds new global settings.
 */

class RestfulSiteConfig extends DataExtension {
	function extraStatics($class = null, $extension = null) {
		return array(
			'db' => array(
				'EnableRESTAPI' => 'Boolean'
			),
			'defaults' => array(
				'EnableRESTAPI' => false
			)
		);
	}

	function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab('Root.Main', new CheckboxField('EnableRESTAPI', 'Enable the REST API'));
	}
}
