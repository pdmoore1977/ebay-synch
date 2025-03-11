<?php
namespace WPLab\Ebay\Models;

class EbayDocument {

	protected int $id = 0;
	protected int $account_id;
	protected int $attachment_id;
	protected \stdClass $attachment;
	protected string $document_id;
	protected string $document_type;
	protected \DateTime $date_added;

	public const DOCUMENT_TYPES_ENUM = [
		'CERTIFICATE_OF_ANALYSIS'       => 'Certificate of Analysis',
		'CERTIFICATE_OF_CONFORMITY'     => 'Certificate of Conformity',
		'DECLARATION_OF_CONFORMITY'     => 'Declaration of Conformity',
		'INSTRUCTIONS_FOR_USE'          => 'Instructions for Use',
		'OTHER_SAFETY_DOCUMENTS'        => 'Other Safety Documents',
		'SAFETY_DATA_SHEET'             => 'Safety Data Sheet',
		'TROUBLE_SHOOTING_GUIDE'        => 'Trouble Shooting Guide',
		'USER_GUIDE_OR_MANUAL'          => 'User Guide or Manual',
		'INSTALLATION_INSTRUCTIONS'     => 'Installation Instructions'
	];

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

	public function getAccountId() {
		return $this->account_id;
	}

	public function setAccountId( $account_id ) {
		$this->account_id = $account_id;
		return $this;
	}

	public function getAttachmentId() {
		return $this->attachment_id;
	}

	public function setAttachmentId( $attachment_id ) {
		$this->attachment_id = $attachment_id;
		return $this;
	}

	public function getAttachment() {
		return $this->attachment;
	}

	protected function setAttachment( $attachment ) {
		$this->attachment = $attachment;
		return $this;
	}

	public function getDocumentId() {
		return $this->document_id;
	}

	public function setDocumentId( $document_id ) {
		$this->document_id = $document_id;
		return $this;
	}

	public function getDocumentType() {
		return $this->document_type;
	}

	public function setDocumentType( $document_type ) {
		$this->document_type = $document_type;
		return $this;
	}

	public function getDateAdded() {
		return $this->date_added;
	}

	public function setDateAdded( \DateTime $date ) {
		$this->date_added = $date;
		return $this;
	}

	/**
	 * Creates a new Document record. Returns the new ID created or false on error
	 * @return int|bool
	 */
	public function save() {
		global $wpdb;

		$data = $this->toArray();

		if ( !empty( $data['id'] ) ) {
			// update existing
			return $this->update();
		}

		$data['date_added'] = current_time('mysql');
		unset($data['id']);

		if ( $wpdb->insert( $wpdb->prefix .'ebay_documents', $data ) ) {
			return $wpdb->insert_id;
		}

		// something went wrong
		WPLE()->logger->error( 'Error saving document. '. $wpdb->last_error );
		WPLE()->logger->debug( print_r( $data, 1 ) );
		return false;
	}

	/**
	 * Updates an existing Document
	 * @return bool
	 */
	public function update() {
		global $wpdb;

		$where  = [ 'id' => $this->getId() ];
		$data   = $this->toArray();

		unset( $data['id'], $data['date_added'] );

		if ( $wpdb->update( $wpdb->prefix .'ebay_documents', $data, $where ) ) {
			return true;
		}

		// something went wrong
		WPLE()->logger->error( 'Error updating document #'. $this->getId(). ': '. $wpdb->last_error );
		WPLE()->logger->debug( print_r( $data, 1 ) );
		return false;
	}

	/**
	 * Deletes a Manufacturer
	 * @return void
	 */
	public function delete() {
		global $wpdb;

		return $wpdb->delete( $wpdb->prefix .'ebay_documents',  ['id' => $this->getId()] );
	}

	protected function toArray() {
		return [
			'id'            => $this->getId(),
			'account_id'    => $this->getAccountId(),
			'attachment_id' => $this->getAttachmentId(),
			'document_id'   => $this->getDocumentId(),
			'document_type' => $this->getDocumentType(),
			'date_added'    => $this->getDateAdded()
		];
	}

	/**
	 * @param int $id
	 * @return object
	 */
	protected function load( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ebay_documents WHERE id = %d", $id ) );
	}
	private function populate( $id ) {
		$row = $this->load( $id );

		if ( $row ) {
			$date_added = new \DateTime( $row->date_added );
			$attachment = get_post( $row->attachment_id );
			$this
				->setId( $id )
				->setAttachmentId( $row->attachment_id )
				->setAttachment( $attachment )
				->setDocumentId( $row->document_id )
				->setDocumentType( $row->document_type )
				->setDateAdded( $date_added );
		}
	}
}