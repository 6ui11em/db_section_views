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

				switch ($field_schema['type']) {
					case 'input':
					case 'textarea':
					case 'reflection':
					case 'order_entries':
						$qry .= $table_name.".value AS ".$element_name.", ";
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
					case 'input':
					case 'textarea':
					case 'reflection':
					case 'order_entries':
					case 'uniqueupload':
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
