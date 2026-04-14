<?php

class Categories{

    //ATTRIBUTES
    private $categoryId;
    private $name;
    private $description;

    //CONSTRUCTOR
    public function __construct($categoryId, $name, $description) {
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->description = $description;
    }

    //GETTERS AND SETTERS
    public function getCategoryId() {
        return $this->categoryId;
    }

    public function getName() {
        return $this->name;
    }

    public function getDescription() {
        return $this->description;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    //METHODS
}