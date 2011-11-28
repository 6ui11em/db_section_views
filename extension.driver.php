<?php

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");
	
	require_once('lib/class.db_section_view.php');
	
	/*
	Copyight: Solutions Nitriques 2011
	License: MIT
	*/
	class extension_db_section_views extends Extension {

		public function about() {
			return array(
				'name'			=> 'Database Section Views',
				'version'		=> '1.0',
				'release-date'	=> '2011-11-22',
				'author'		=> array(
					'name'			=> 'Vaughan Hale | Markitable New Zealand',
					'website'		=> 'http://www.markitable.co.nz',
					'email'			=> 'support (at) markitable.co.nz'
				),
				'description'	=> '',
				'compatibility' => array(
					'2.2.3' => true
				)
	 		);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPostCreate',
					'callback' => 'createView'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPostEdit',
					'callback' => 'createView'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPreDelete',
					'callback' => 'dropView'
				),
			);
		}
		
		public function install() {
			$sections = $this->getAllSections();
			$section_view = new DBSectionView();
			
			foreach ($sections as $section) {
				$section_view->createView($section['id']);
			}
						
			return true;
		}
		
		public function uninstall() {
			$sections = $this->getAllSections();
			$section_view = new DBSectionView();
			
			foreach ($sections as $section) {
				$section_view->dropSectionViews($section['id']);
			}
		}
		
		public function createView($context) {
			$section_id = $context['section_id'];
			$errors = Administration::instance()->Page->_errors;
			
			$section_view = new DBSectionView();
			$section_view->createView($section_id);
		}
		
		public function dropView($context) {
			$sections = $context['section_ids'];
			$section_view = new DBSectionView();
			
			foreach ($sections as $section_id) {
				$section_view->dropSectionViews($section_id);
			}
		}
		
		function getAllSections() {
	     	$sections = Symphony::Database()->fetch("SELECT `id`, `handle` FROM `tbl_sections`");
	     	
	     	return $sections;
		}

	}
	
?>