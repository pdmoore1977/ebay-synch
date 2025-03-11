<?php
namespace WPLab\Ebay\Models;
class EbayAddress {

	protected string $company = '';
	protected string $street1 = '';
	protected string $street2 = '';
	protected string $city = '';
	protected string $state = '';
	protected string $postcode = '';
	protected string $country = '';
	protected string $phone = '';
	protected string $email = '';

	public function __construct( $id = null ) {

	}

	public function getCompany() {
		return $this->company;
	}

	public function setCompany( $company ) {
		$this->company = $company;
		return $this;
	}

	public function getStreet1() {
		return $this->street1;
	}

	public function setStreet1( $street1 ) {
		$this->street1 = $street1;
		return $this;
	}

	public function getStreet2() {
		return $this->street2;
	}

	public function setStreet2( $street2 ) {
		$this->street2 = $street2;
		return $this;
	}

	public function getCity() {
		return $this->city;
	}

	public function setCity( $city ) {
		$this->city = $city;
		return $this;
	}

	public function getState() {
		return $this->state;
	}

	public function setState( $state ){
		$this->state = $state;
		return $this;
	}

	public function getPostcode() {
		return $this->postcode;
	}

	public function setPostcode( $postcode ) {
		$this->postcode = $postcode;
		return $this;
	}

	public function getCountry() {
		return $this->country;
	}

	public function setCountry( $country ) {
		$this->country = $country;
		return $this;
	}

	public function getEmail() {
		return $this->email;
	}

	public function setEmail( $email ) {
		$this->email = $email;
		return $this;
	}

	public function getPhone() {
		return $this->phone;
	}

	public function setPhone( $phone ) {
		$this->phone = $phone;
		return $this;
	}

	protected function toArray() {
		return [
			'street1'   => $this->getStreet1(),
			'street2'   => $this->getStreet2(),
			'city'      => $this->getCity(),
			'state'     => $this->getState(),
			'postcode'  => $this->getPostcode(),
			'country'   => $this->getCountry(),
			'email'     => $this->getEmail(),
			'phone'     => $this->getPhone(),
			'company'   => $this->getCompany()
		];
	}
}