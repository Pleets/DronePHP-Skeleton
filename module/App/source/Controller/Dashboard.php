<?php

namespace App\Controller;

use Drone\Mvc\View;
use Drone\Mvc\AbstractController;

class Dashboard extends AbstractController
{
	public function index()
	{
        return new View("index", ["framework" => "DronePHP"]);
	}
}