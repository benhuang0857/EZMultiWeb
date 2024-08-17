<?php

namespace App\Admin\Controllers;

use App\WebSites;
use App\Template;
use Encore\Admin\Controllers\AdminController;
Use Encore\Admin\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use GuzzleHttp\Client;
use ZipArchive;

class WebSitesController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'WebSites';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new WebSites());
        $grid->column('id', __('Id'));
        $grid->column('cid', '客戶');
        $grid->column('template_id', '版型');
        $grid->column('site_name', '網站名稱');
        $grid->column('domain', '網址');
        $grid->column('db_port', 'DB Port');
        $grid->column('site_port', 'Site Port');
        $grid->column('state', '狀態')->display(function($state){
            $stateList = [
                'init'          => '建立新服務',
                'creating'      => '服務啟動中',
                'building'      => '服務建置中',
                'running'       => '服務運行中',
                'stop'          => '服務暫停',
                'error'         => '服務異常',
                'kill'          => '服務關閉',
            ];
            $stateLight = [
                'init'          => 'green',
                'creating'      => 'blue',
                'building'      => 'orange',
                'running'       => 'green',
                'stop'          => 'orange',
                'error'         => 'red',
                'kill'          => 'gray',
            ];
            return '<span class="badge badge-warning" style="background:'.$stateLight[$state].'">'.$stateList[$state].'</span>';
        });
        $grid->column('created_at', '建立時間');
        $grid->column('updated_at', '更新時間');

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(WebSites::findOrFail($id));
        $show->field('id', __('Id'));
        $show->field('cid', __('Cid'));
        $show->field('template_id', 'template_id');
        $show->field('site_name', __('Site Name'));
        $show->field('domain', __('Domain'));
        $show->field('db_port', __('Db port'));
        $show->field('site_port', __('Site port'));
        $show->field('state', __('State'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    protected function get_docker_status()
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/docker-status');
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200 && $data['data'] == 'Docker daemon is alive')
            {
                $viewBag = [
                    'code' => true,
                    'data' => 'Docker daemon is alive'
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    protected function check_directory($dirname)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $targetDirectory = realpath($currentDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . $dirname;
        
        if (!is_dir($targetDirectory)) {
            $viewBag = [
                'code' => true,
                'data' => 'directory not exists, can create directory'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'directory already exists'
            ];
        }
        return $viewBag;
    }

    protected function check_sitename($site_name)
    {
        $viewBag = [];
        $website = WebSites::where('site_name', $site_name)->first();

        if ($website == null) {
            $viewBag = [
                'code' => true,
                'data' => 'site name does not exist, can create site'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'site name already exists'
            ];
        }

        return $viewBag;
    }

    protected function unzip_and_rename($zip_file, $new_folder_name)
    {
        $viewBag = [];
        $zip = new ZipArchive;
        $currentDirectory = getcwd();
        $targetZip = realpath($currentDirectory . '/../..') . '/' . $zip_file;
        $targetDirectory = realpath($currentDirectory . '/../..') . '/' . $new_folder_name;

        if ($zip->open($targetZip) === TRUE) {
            mkdir($targetDirectory, 0777, true);
            $zip->extractTo($targetDirectory);
            $zip->close();
            $viewBag = [
                'code' => true,
                'data' => 'unzip successful'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'failed to unzip'
            ];
        }

        return $viewBag;
    }

    protected function copy_env($site_name, $site_port, $db_port)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $envOrg = $sourcePath . '/.env.org';

        if (!file_exists($envOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original env file not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($envOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read env file'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{project_name}}', '{{site_port}}', '{{sql_port}}'],
            [$site_name, $site_port, $db_port],
            $content
        );

        if (file_put_contents($sourcePath . '/.env', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write env file'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'env file copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    protected function copy_wp_auto_config($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wp-auto-config.yml.org';

        if (!file_exists($fileOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original wp-auto-config file not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($fileOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read wp-auto-config file'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{template_id}}'],
            [$template_id],
            $content
        );

        if (file_put_contents($sourcePath . '/wp-auto-config.yml', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write wp-auto-config file'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'wp-auto-config file copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    protected function copy_template($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $theme_wpress = "default-ai1wm-stable-" . $template_id . ".wpress";
        $sourceFile = realpath($currentDirectory . '/../..') . '/theme' . '/' . $theme_wpress;
        $targetPath = realpath($currentDirectory . '/../..') . '/' . $site_name . '/config/wordpress/' . $theme_wpress;

        if (!file_exists($sourceFile)) {
            $viewBag = [
                'code' => false,
                'data' => 'source template file not found'
            ];
        } elseif (copy($sourceFile, $targetPath)) {
            $viewBag = [
                'code' => true,
                'data' => 'template file copied successfully'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'failed to copy template file'
            ];
        }

        return $viewBag;
    }

    protected function copy_make_file($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wpcli/Makefile.org';

        if (!file_exists($fileOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original Makefile not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($fileOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read Makefile'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{template_id}}'],
            [$template_id],
            $content
        );

        if (file_put_contents($sourcePath . '/wpcli/Makefile', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write Makefile'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'Makefile copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    protected function kill_directory($site_name) 
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $target_dir = realpath($currentDirectory . '/../..') . '/' . $site_name;

        if (!file_exists($target_dir) || !is_dir($target_dir)) {
            $viewBag = [
                'code' => false,
                'data' => 'Folder not found'
            ];
            return $viewBag;
        }
    
        if ($this->deleteDirectory($target_dir)) {
            $viewBag = [
                'code' => true,
                'data' => 'Folder deleted successfully'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'Failed to delete folder'
            ];
        }
        
        return $viewBag;
    }

    private function deleteDirectory($dir) 
    {
        if (!file_exists($dir)) {
            return false;
        }
    
        if (!is_dir($dir)) {
            return unlink($dir);
        }
    
        $items = array_diff(scandir($dir), ['.', '..']);
    
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    protected function init_containers($site_name)
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/init-containers?site_name='.$site_name);
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200)
            {
                $viewBag = [
                    'code' => true,
                    'data' => $data['data']
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    protected function stop_containers($site_name)
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/stop-containers?site_name='.$site_name);
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200)
            {
                $viewBag = [
                    'code' => true,
                    'data' => $data['data']
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    protected function kill_containers($site_name)
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/kill-containers?site_name='.$site_name);
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200)
            {
                $viewBag = [
                    'code' => true,
                    'data' => $data['data']
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    protected function kill_volumes()
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/kill-volumes');
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200)
            {
                $viewBag = [
                    'code' => true,
                    'data' => $data['data']
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    ////
    protected function handleInitState($form, $site_name, $site_port, $db_port, $template_id, $zip_source_file)
    {
        if ($this->check_directory($site_name)['code'] &&
            $this->unzip_and_rename($zip_source_file, $site_name)['code'] &&
            $this->copy_env($site_name, $site_port, $db_port)['code'] &&
            $this->copy_wp_auto_config($site_name, $template_id)['code'] &&
            $this->copy_make_file($site_name, $template_id)['code'] &&
            $this->copy_template($site_name, $template_id)['code']) {
            $form->state = "building";
        } else {
            $form->state = "error";
        }
    }

    protected function handleBuildingState($form, $site_name)
    {
        if (!$this->check_directory($site_name)['code'] &&
            !$this->check_sitename($site_name)['code'] &&
            $this->init_containers($site_name)['code']) {
            $form->state = "running";
        } else {
            $form->state = "error";
        }
    }

    protected function handleStopState($form, $site_name)
    {
        if (!$this->check_directory($site_name)['code'] &&
            !$this->check_sitename($site_name)['code'] &&
            $this->stop_containers($site_name)['code']) {
            $form->state = "stop";
        } else {
            $form->state = "error";
        }
    }

    protected function handleKillState($form, $site_name)
    {
        if (!$this->check_directory($site_name)['code'] &&
            !$this->check_sitename($site_name)['code'] &&
            $this->kill_containers($site_name)['code'] &&
            $this->kill_directory($site_name)['code']) {
            $this->kill_volumes();
            $form->state = "kill";
        } else {
            $form->state = "error";
        }
    }

    protected function form()
    {
        Admin::style('
            .radio {
                float: left;
                margin: 5px;
                border-radius: 5px;
                border: 3px solid black;
            }
        ');
        $templateAll = Template::all();
        $templateArr = array();
        foreach($templateAll as $item)
        {
            $img_url = "<img width=300 src='/storage/".$item->image_path."'";
            $templateArr[$item->allinone_name] = $img_url;
        }

        $form = new Form(new WebSites());
        $form->text('cid', '客戶');
        $form->text('site_name', '網站名稱');
        $form->radio('template_id', '版型')->options($templateArr)->stacked();
        $form->text('domain', '網址');
        $form->text('db_port', 'DB Port');
        $form->text('site_port', 'Site Port');
        $form->select('state', '狀態')->options([ // init/creating/building/running/stop/error/kill
            'init'          => '建立新服務',
            'building'      => '建置服務',
            'stop'          => '服務暫停',
            'kill'          => '服務關閉',
        ]);

        $form->saving(function (Form $form) {
            $site_name   = $form->site_name;
            $site_port   = $form->site_port;
            $db_port     = $form->db_port;
            $template_id = $form->template_id;
        
            $zip_source_file = 'wp-ezweb.zip';
            $resp = $this->get_docker_status();
        
            if ($resp['code'] != true) {
                $form->state = "error";
                return;
            }
        
            switch ($form->state) {
                case 'init':
                    $this->handleInitState($form, $site_name, $site_port, $db_port, $template_id, $zip_source_file);
                    $this->handleBuildingState($form, $site_name);
                    break;
        
                case 'building':
                    $this->handleBuildingState($form, $site_name);
                    break;
        
                case 'stop':
                    $this->handleStopState($form, $site_name);
                    break;
        
                case 'kill':
                    $this->handleKillState($form, $site_name);
                    break;
            }
        });

        return $form;
    }
}