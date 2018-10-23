<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class JsonWs extends Module
{
    public function __construct()
    {
        $this->name = 'jsonws';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Simone Fuoco';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => '1.8'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Json WebService');
        $this->description = $this->l('Enable to put and post json to webservice');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
}
