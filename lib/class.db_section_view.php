<?php

	require_once(TOOLKIT . '/class.section.php');

	Class DBSectionView {
		
		var $_section;
		var $handle;
		
		function __construct(){
			$this->_section = new Section($this);
		}
	
		function createView($section_id) {
			$this->setIdAndHandle($section_id);
			$section_fields_schema = $this->_section->fetchFieldsSchema();
			
			$qry = "CREATE OR REPLACE VIEW tbl_sv_".$this->handle." AS SELECT section.id AS id, section.section_id AS section_id, ";

			foreach ($section_fields_schema as $field_schema) {
				$element_name = $this->handle."_".str_replace("-", "_", $field_schema['element_name']);
				$table_name = "tbl_entries_data_".$field_schema['id'];
				$field_type = $field_schema['type'];

				switch ($field_type) {
					case 'datetime':
						$qry .= $table_name.".start AS ".$element_name."_start, ";
						$qry .= $table_name.".end AS ".$element_name."_end, ";
					break;
					case 'date':
					case 'datemodified':
					case 'input':
					case 'uniquetext':
					case 'textarea':
					case 'number':
					case 'reflection':
					case 'order_entries':
					case 'status':
						$qry .= $table_name.".value AS ".$element_name.", ";
					break;
					case 'multilingual':
						$supported_language_codes = General::Sanitize(Symphony::Configuration()->get('language_codes', 'language_redirect'));

						// Support for older versions of Language Redirect
						if (empty($supported_language_codes)) {
							$supported_language_codes = General::Sanitize(Symphony::Configuration()->get('language_codes', 'languages'));
						}
						
						$supported_language_codes = preg_split('/\s*,\s*/', $supported_language_codes);
						
						$qry .= $table_name.".value AS ".$element_name.", ";
						foreach ($supported_language_codes as $lang) {				
							$qry .= $table_name.".`value-".$lang."` AS ".$element_name."_".$lang.", ";
						}
					break;
					case 'selectbox_link':
						if ($this->isMultiple($field_schema['id'], 'selectbox_link') ) {
							$field_mapping = array(
								 "id" => "id"
								,"entry_id" => $this->handle."_id"
								,"relation_id" => str_replace("-", "_", $field_schema['element_name'])."_id"
							);
							
							$element_name = $this->handle."__".str_replace("-", "_", $field_schema['element_name']);
							$this->createRelationView($table_name, $element_name, $field_mapping);
						} else {
							$qry .= $table_name.".relation_id AS ".$element_name.", ";
						}
					break;
					case 'uniqueupload':
						$qry .= $table_name.".file AS ".$element_name.", ";
					break;
					case 'subsectionmanager':
						$field_mapping = array(
							 "id" => "id"
							,"entry_id" => $this->handle."_id"
							,"relation_id" => str_replace("-", "_", $field_schema['element_name'])."_id"
						);
						
						$element_name = $this->handle."__".str_replace("-", "_", $field_schema['element_name']);
						$this->createRelationView($table_name, $element_name, $field_mapping);
						break;
					case 'taglist':
						$field_mapping = array(
							 "id" => "id"
							,"entry_id" => $this->handle."_id"
							,"handle" => "handle"
							,"value" => "name"
						);
						
						$element_name = $this->handle."__".str_replace("-", "_", $field_schema['element_name']);
						$this->createRelationView($table_name, $element_name, $field_mapping);
					break;
					
					case 'xml_selectbox':
						$field_mapping = array(
							 "id" => "id"
							,"entry_id" => $this->handle."_id"
							,"value" => "value"
						);
						
						$element_name = $this->handle."__".str_replace("-", "_", $field_schema['element_name']);
						$this->createRelationView($table_name, $element_name, $field_mapping);
					break;
					
					default:
						;
					break;
				}
			}
			$qry = substr($qry, 0, -2);	
			$qry .= " FROM sym_entries AS section ";

			foreach ($section_fields_schema as $field_schema) {
				$element_name = str_replace("-", "_", $field_schema['element_name']);
				$table_name = "tbl_entries_data_".$field_schema['id'];
				
				switch ($field_schema['type']) {
					case 'datetime':
					case 'date':
					case 'datemodified':
					case 'input':
					case 'uniquetext':
					case 'number':
					case 'textarea':
					case 'reflection':
					case 'order_entries':
					case 'uniqueupload':
					case 'multilingual':
					case 'status':
						$qry .= " LEFT JOIN ".$table_name." ON ".$table_name.".entry_id=section.id";
					break;
					case 'selectbox_link':
						if (!$this->isMultiple($field_schema['id'], 'selectbox_link'))
							$qry .= " LEFT JOIN ".$table_name." ON ".$table_name.".entry_id=section.id";
					break;
					case 'subsectionmanager':
						//$qry .= " LEFT JOIN ".$table_name." ON ".$table_name.".entry_id=section.id";
					break;
					default:
						;
					break;
				}
			}
			$qry .= " WHERE section.section_id = ".$section_id;
			$qry .= " GROUP BY section.id";
			
			Symphony::Database()->query($qry);
		}
		
		function setIdAndHandle($section_id) {
	     	$section = Symphony::Database()->fetch("SELECT `id`, `handle` FROM `tbl_sections` WHERE `id` = '".$section_id."'LIMIT 1");
	     	$this->_section->set('id', $section[0]['id']);
	     	$this->handle = str_replace("-", "_", $section[0]['handle']);
		}
		
		function isMultiple($id, $field_type) {
	     	$allow_multiple = Symphony::Database()->fetchVar("allow_multiple_selection", 0, "SELECT `allow_multiple_selection` FROM `tbl_fields_".$field_type."` WHERE `field_id` = '".$id."' LIMIT 1");

				return $allow_multiple == 'yes';
		}
		
		function createRelationView($table_name, $view_name, $field_mapping){
			$qry = "CREATE OR REPLACE VIEW tbl_sv_".$view_name." AS SELECT";
			
			foreach ($field_mapping as $field => $field_alias) {
				$qry .= " ".$table_name.".".$field." AS ".$field_alias;
				
				if (next($field_mapping)==true) $qry .= ", ";
			}
			$qry .= " FROM ".$table_name;
			
			Symphony::Database()->query($qry);
		}
		
		function dropSectionViews($section_id) {
			$this->setIdAndHandle($section_id);
			
			$qry = "DROP VIEW IF EXISTS tbl_sv_".$this->handle;
	     	Symphony::Database()->query($qry);
			
			$section_fields_schema = $this->_section->fetchFieldsSchema();

			foreach ($section_fields_schema as $field_schema) {
				$element_name = $this->handle."__".str_replace("-", "_", $field_schema['element_name']);
				$table_name = "tbl_entries_data_".$field_schema['id'];

				switch ($field_schema['type']) {
					case 'subsectionmanager':
					case 'taglist':
						$qry = "DROP VIEW IF EXISTS tbl_sv_".$element_name;
						
						Symphony::Database()->query($qry);
					break;
				}
			}
		}
		
	
	}
