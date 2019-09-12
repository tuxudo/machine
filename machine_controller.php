<?php

/**
 * Machine module class
 *
 * @package munkireport
 * @author
 **/
class Machine_controller extends Module_controller
{

    /*** Protect methods with auth! ****/
    public function __construct()
    {
        if (! $this->authorized()) {
            die('Authenticate first.'); // Todo: return json?
        }

        // Store module path
        $this->module_path = dirname(__FILE__) .'/';
        $this->view_path = $this->module_path . 'views/';
    }

    /**
     * Default method
     *
     * @author AvB
     **/
    public function index()
    {
        echo "You've loaded the hardware module!";
    }

    /**
     * Get duplicate computernames
     *
     *
     **/
    public function get_duplicate_computernames()
    {
        $machine = Machine_model::selectRaw('computer_name, COUNT(*) AS count')
            ->filter()
            ->groupBy('computer_name')
            ->having('count', '>', 1)
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    
        $obj = new View();
        $obj->view('json', ['msg' => $machine]);
    }

    /**
     * Get model statistics
     *
     **/
    public function get_model_stats($summary="")
    {
        $machine = Machine_model::selectRaw('count(*) AS count, machine_desc AS label')
            ->filter()
            ->groupBy('machine_desc')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        $out = array();
        foreach ($machine as $obj) {
            $obj['label'] = $obj['label'] ? $obj['label'] : 'Unknown';
            $out[] = $obj;
        }

        // Check if we need to convert to summary (Model + screen size)
        if($summary){
            $model_list = array();
            foreach ($out as $key => $obj) {
                // Mac mini Server (Late 2012)
                //
                $suffix = "";
                if(preg_match('/^(.+) \((.+)\)/', $obj['label'], $matches))
                {
                    $name = $matches[1];
                    // Find suffix
                    if(preg_match('/([\d\.]+-inch)/', $matches[2], $matches))
                    {
                        $suffix = ' ('.$matches[1].')';
                    }
                }
                else
                {
                    $name = $obj['label'];

                }
                if(! isset($model_list[$name.$suffix]))
                {
                    $model_list[$name.$suffix] = 0;
                }
                $model_list[$name.$suffix] += $obj['count'];

            }
            // Erase out
            $out = array();
            // Sort model list
            arsort($model_list);
            // Add entries to $out
            foreach ($model_list as $key => $count)
            {
                $out[] = array('label' => $key, 'count' => $count);
            }
        }
        $obj = new View();
        $obj->view('json', ['msg' => $out]);
    }


    /**
     * Get machine data for a particular machine
     *
     * @return void
     * @author
     **/
    public function report($serial_number = '')
    {
        $machine = Machine_model::where('machine.serial_number', $serial_number)
            ->filter()
            ->first();
        $obj = new View();
        $obj->view('json', array('msg' => $machine));
    }

    /**
     * Return new clients
     *
     * @return void
     * @author
     **/
    public function new_clients()
    {
        $lastweek = time() - 60 * 60 * 24 * 7;
        $out = Machine_model::select('machine.serial_number', 'computer_name', 'reg_timestamp')
            ->where('reg_timestamp', '>', $lastweek)
            ->filter()
            ->orderBy('reg_timestamp', 'desc')
            ->get()
            ->toArray();

        $obj = new View();
        $obj->view('json', array('msg' => $out));
    }

    /**
     * Return json array with memory configuration breakdown
     *
     * @param string $format Format output. Possible values: flotr, none
     * @author AvB
     **/
    public function get_memory_stats($format = 'none')
    {
        $out = array();

        // Legacy loop to do sort in php
        $tmp = array();
        $machine = Machine_model::selectRaw('physical_memory, count(1) as count')
            ->filter()
            ->groupBy('physical_memory')
            ->orderBy('physical_memory', 'desc')
            ->get()
            ->toArray();
        
        foreach ($machine as $obj) {
        // Take care of mixed entries (string or int)
            if (isset($tmp[$obj['physical_memory']])) {
                $tmp[$obj['physical_memory']] += $obj['count'];
            } else {
                $tmp[$obj['physical_memory']] = $obj['count'];
            }
        }

        switch ($format) {
            case 'flotr':
                krsort($tmp);

                $cnt = 0;
                foreach ($tmp as $mem => $memcnt) {
                    $out[] = array('label' => $mem . ' GB', 'data' => array(array(intval($memcnt), $cnt++)));
                }
                break;

            default:
                foreach ($tmp as $mem => $memcnt) {
                    $out[] = array('label' => $mem, 'count' => intval($memcnt));
                }
        }

        $obj = new View();
        $obj->view('json', array('msg' => $out));
    }

    /**
     * Return json array with hardware configuration breakdown
     *
     * @author AvB
     **/
    public function hw()
    {
        $out = [];
        $machine = Machine_model::selectRaw('machine_name, count(1) as count')
            ->filter()
            ->groupBy('machine_name')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
        foreach ($machine as $obj) {
            $out[] = array('label' => $obj['machine_name'], 'count' => intval($obj['count']));
        }

        $obj = new View();
        $obj->view('json', array('msg' => $out));
    }

    /**
     * Return json array with os breakdown
     *
     * @author AvB
     **/
    public function os()
    {
        $obj = new View();
        $obj->view('json', [
            'msg' => $this->_trait_stats('os_version')
        ]);
    }
    /**
     * Return json array with os build breakdown
     *
     * @author AkB
     **/
    public function osbuild()
    {
        $obj = new View();
        $obj->view('json', [
            'msg' => $this->_trait_stats('buildversion')
        ]);
    }

    private function _trait_stats($what = 'os_version'){
        $out = [];
        $machine = Machine_model::selectRaw("count(1) as count, $what")
            ->filter()
            ->groupBy($what)
            ->orderBy($what, 'desc')
            ->get()
            ->toArray();

        foreach ($machine as $obj) {
            $obj[$what] = $obj[$what] ? $obj[$what] : '0';
            $out[] = ['label' => $obj[$what], 'count' => intval($obj['count'])];
        }
        return $out;
    }
} // END class Machine_controller
