<?php

class Users{

    //ATTRIBUTES
    private $userId;
    private $name;
    private $email;
    private $password;
    private $role;
    private $userCreatedAt;

    //CONSTRUCTOR
    public function __construct($userId, $name, $email, $password, $role, $userCreatedAt) {
        $this->userId = $userId;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->userCreatedAt = $userCreatedAt;
    }

    //GETTERS AND SETTERS
    public function getUserId() {
        return $this->userId;
    }

    public function getName() {
        return $this->name;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getRole() {
        return $this->role;
    }

    public function getUserCreatedAt() {
        return $this->userCreatedAt;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    public function setRole($role) {
        $this->role = $role;
    }

    public function setUserCreatedAt($userCreatedAt) {
        $this->userCreatedAt = $userCreatedAt;
    }

    //METHODS

}