<?php

namespace App\Controller;

use Drone\Mvc\AbstractionController;

class Matched extends AbstractionController
{
	public function doIt()
	{
		$this->setTerminal(true);

		echo "hello world!";

		return [];
	}
}