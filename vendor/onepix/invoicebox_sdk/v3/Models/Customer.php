<?php

namespace Invoicebox\V3\Models;

use Invoicebox\Contracts\Models\CustomerInterface;
use Invoicebox\Exceptions\NotValidCustomerData;

class Customer implements CustomerInterface
{
    private $type;
    private $name;
    private $phone;
    private $email;
    private $vatNumber;
    private $registrationAddress;
	private $kpp;

    /**
     * Customer constructor.
     * @param string|null $type
     * @param string|null $name
     * @param string|null $phone
     * @param string|null $email
     * @param string|null $inn
     * @param string|null $address
     * @param string|null $kpp
     * @throws NotValidCustomerData
     */
    public function __construct(string $type=null, string $name=null, string $phone=null, string $email=null, string $inn=null, string $address=null, string $kpp = null)
    {
        if(!is_null($type)) $this->setType($type);
        if(!is_null($name)) $this->setName($name);
        if(!is_null($phone)) $this->setPhone($phone);
        if(!is_null($email)) $this->setEmail($email);
        if(!is_null($inn)) $this->setInn($inn);
        if(!is_null($address)) $this->setAddress($address);
		if(!is_null($kpp)) $this->setKpp($kpp);
    }

    public function setType(string $type)
    {
        switch ($type){
            case self::PRIVATE_PERSON: $this->type = self::PRIVATE_PERSON; break;
            case self::LEGAL_PERSON: $this->type = self::LEGAL_PERSON; break;
            default: throw new NotValidCustomerData("Тип клиента задан неправильно");
        }
    }

    public function setName(string $name)
    {
        if(empty($name)) throw new NotValidCustomerData("Имя является обязательным полем");
        if(strlen($name) > 500) throw new NotValidCustomerData("Имя не должно быть длиннее 500 знаков");
        $this->name = $name;
    }

    public function setPhone(string $phone)
    {
        if(empty($phone)) throw new NotValidCustomerData("Телефон является обязательным полем");
        if(strlen($phone) > 100) throw new NotValidCustomerData("Телефон не должен быть длиннее 500 знаков");
        $this->phone = $phone;
    }

    public function setEmail(string $email)
    {
        if(empty($email)) throw new NotValidCustomerData("Email является обязательным полем");
        if(strlen($email) > 100) throw new NotValidCustomerData("Email не должен быть длиннее 100 знаков");
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new NotValidCustomerData("Email имеет неправильный формат");
        $this->email = $email;
    }

    public function setInn(string $inn)
    {
        if(empty($inn)) return;
        if(strlen($inn) > 20) throw new NotValidCustomerData("ИНН не должен быть длиннее 20 знаков");
        $this->vatNumber = strval($inn);
    }

    public function setAddress(string $address)
    {
        if(empty($address)) return;
        if(strlen($address) > 1000) throw new NotValidCustomerData("ИНН не должен быть длиннее 1000 знаков");
        $this->registrationAddress = $address;
    }

	public function setKpp(string $kpp)
	{
		if(empty($kpp)) return;
		if(strlen($kpp) > 1000) throw new NotValidCustomerData("КПП не должен быть длиннее 100 знаков");
		$this->kpp = $kpp;
	}

    /**
     * @return array
     * @throws NotValidCustomerData
     */
    public function formData(): array
    {
        $data = [];
        $required_fields = [
            "type",
            "name",
            "phone",
            "email",
        ];

        if($this->type === self::LEGAL_PERSON){
            $required_fields[] = "vatNumber";
            $required_fields[] = "registrationAddress";
			$required_fields[] = "kpp";
        }

        $not_filled = [];

        foreach ($required_fields as $field) {
            if(is_null($this->$field)) $not_filled[] = $field;
            else $data[$field] = $this->$field;
        }

        if(!empty($not_filled)) throw new NotValidCustomerData(sprintf("Значения полей %s должны быть заполнены.", implode(", ", $not_filled)));

        return $data;
    }
}
