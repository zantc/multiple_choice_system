<?php
/**
 * Home Controller
 */

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->view('home');
    }
}
