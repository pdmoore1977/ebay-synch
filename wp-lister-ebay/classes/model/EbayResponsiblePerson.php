<?php

namespace WPLab\Ebay\Models;
class EbayResponsiblePerson extends EbayAddress {
	protected int $id = 0;
	protected string $date_added = '';

	public function __construct( $id = null ) {
		if ( $id ) {
			$this->populate( $id );
		}
	}

	public function getId() {
		return $this->id;
	}

	public function setId( $id ) {
		$this->id = $id;
		return $this;
	}

	public function getDateAdded() {
		return $this->date_added;
	}

	public function setDateAdded( $date ) {
		$this->date_added = $date;
		return $this;
	}

	/**
	 * Creates a new Manufacturer record. Returns the new ID created or false on error
	 * @return int|bool
	 */
	public function save() {
		global $wpdb;

		$data = $this->toArray();

		if ( !empty( $data['id'] ) ) {
			// update existing
			return $this->update();
		}

		if ( $this->exists() ) {
			return new \WP_Error( 'duplicate_data', __('A Responsible Person with the same Company and Email already exists.', 'wp-lister-for-ebay') );
		}

		$data['date_added'] = current_time('mysql');
		unset($data['id']);

		if ( $wpdb->insert( $wpdb->prefix .'ebay_responsible_persons', $data ) ) {
			return $wpdb->insert_id;
		}

		// something went wrong
		WPLE()->logger->error( 'Error saving person. '. $wpdb->last_error );
		WPLE()->logger->debug( print_r( $data, 1 ) );
		return new \WP_Error( 'responsible_person_error', __('Error saving this Responsible Person. Please try again or contact support.', 'wp-lister-for-ebay') );
	}

	/**
	 * Updates an existing Manufacturer
	 * @return bool
	 */
	public function update() {
		global $wpdb;

		$where  = [ 'id' => $this->getId() ];
		$data   = $this->toArray();

		unset( $data['id'], $data['date_added'] );

		if ( $wpdb->update( $wpdb->prefix .'ebay_responsible_persons', $data, $where ) ) {
			return true;
		}

		// something went wrong
		WPLE()->logger->error( 'Error updating person #'. $this->getId(). ': '. $wpdb->last_error );
		WPLE()->logger->debug( print_r( $data, 1 ) );
		return false;
	}

	/**
	 * Deletes a Manufacturer
	 * @return void
	 */
	public function delete() {
		global $wpdb;

		return $wpdb->delete( $wpdb->prefix .'ebay_responsible_persons',  ['id' => $this->getId()] );
	}

	public function exists() {
		global $wpdb;

		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*)
			FROM `{$wpdb->prefix}ebay_responsible_persons`
			WHERE company = %s
			AND email = %s",
			$this->getCompany(),
			$this->getEmail()
		));

		return $count > 0;
	}

	protected function toArray() {
		$data = parent::toArray();
		$data['id'] = $this->getId();
		$data['date_added'] = $this->getDateAdded();
		return $data;
	}

	/**
	 * @param int $id
	 * @return object
	 */
	protected function load( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ebay_responsible_persons WHERE id = %d", $id ) );
	}
	private function populate( $id ) {
		$row = $this->load( $id );

		if ( $row ) {
			$this
				->setId( $id )
				->setCompany( $row->company )
				->setStreet1( $row->street1 )
				->setStreet2( $row->street2 )
				->setCity( $row->city )
				->setState( $row->state )
				->setPostcode( $row->postcode )
				->setCountry( $row->country )
				->setEmail( $row->email )
				->setPhone( $row->phone )
				->setDateAdded( $row->date_added );
		}
	}
}