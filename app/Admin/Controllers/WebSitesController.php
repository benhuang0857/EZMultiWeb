<?php

namespace App\Admin\Controllers;

use App\WebSites;
use Encore\Admin\Controllers\AdminController;
Use Encore\Admin\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use App\Template;
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
                'creating'  => '檔案建立中',
                'burn-up'   => '服務啟動中',
                'running'   => '服務運行中',
                'stop'      => '服務暫停',
                'error'     => '服務異常',
                'shutdown'  => '服務關閉',
            ];
            $stateLight = [
                'creating'  => 'green',
                'burn-up'   => 'green',
                'running'   => 'green',
                'stop'      => 'yellow',
                'error'     => 'red',
                'shutdown'  => 'gray',
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

    function checkDirectoryExists($directoryName)
    {
        $currentDirectory = getcwd();
        $targetDirectory = realpath($currentDirectory . '/../..') . '/' . $directoryName;
        if (is_dir($targetDirectory)) {
            return true;
        } else {
            return false;
        }
    }

    function checkSiteNameExists($siteName)
    {
        $website = WebSites::where('site_name', $siteName)->first();
        if ($website != null) {
            return true;
        } else {
            return false;
        }
    }

    public function unzipAndRename($zipFile, $newFolderName) 
    {
        $zip = new ZipArchive;
        $currentDirectory = getcwd();
        $targetZip = realpath($currentDirectory . '/../..') . '/' . $zipFile;
        $targetDirectory = realpath($currentDirectory . '/../..') . '/' . $newFolderName;
    
        if ($zip->open($targetZip) === TRUE) { // 打開 zip 檔案
            mkdir($targetDirectory, 0777, true);
            $zip->extractTo($targetDirectory);
            $zip->close();

            return true;
        } else {
            return false;
        }
    }

    public function copyEnv($site_name, $site_port, $db_port) 
    {
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $envOrg = $sourcePath . '/.env.org';

        try {
            if (!file_exists($envOrg)) {
                return false;
            }
    
            $content = file_get_contents($envOrg);
            if ($content === false) {
                return false;
            }
    
            $replaced = str_replace(
                ['{{project_name}}', '{{site_port}}', '{{sql_port}}'],
                [$site_name, $site_port, $db_port],
                $content
            );
    
            $result = file_put_contents($sourcePath . '/.env', $replaced);
            if ($result === false) {
                return false;
            }
    
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function copyWpAutoConfig($site_name, $template_id) 
    {
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wp-auto-config.yml.org';

        try {
            if (!file_exists($fileOrg)) {
                return false;
            }
    
            $content = file_get_contents($fileOrg);
            if ($content === false) {
                return false;
            }
    
            $replaced = str_replace(
                ['{{template_id}}'],
                [$template_id],
                $content
            );
    
            $result = file_put_contents($sourcePath . '/wp-auto-config.yml', $replaced);
            if ($result === false) {
                return false;
            }
    
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function copyMakeFile($site_name, $template_id) 
    {
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wpcli/Makefile.org';

        try {
            if (!file_exists($fileOrg)) {
                return false;
            }
    
            $content = file_get_contents($fileOrg);
            if ($content === false) {
                return false;
            }
    
            $replaced = str_replace(
                ['{{template_id}}'],
                [$template_id],
                $content
            );
    
            $result = file_put_contents($sourcePath . '/wpcli/Makefile', $replaced);
            if ($result === false) {
                return false;
            }
    
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function copyTemplate($site_name, $template_id) 
    {
        $currentDirectory = getcwd();
        $theme_wpress = "default-ai1wm-stable-".$template_id.".wpress";
        $sourceFile = realpath($currentDirectory . '/../..') . '/theme'. '/' . $theme_wpress;
        $targetPath = realpath($currentDirectory . '/../..') . '/' . $site_name . '/config/wordpress/' . $theme_wpress;
   
        try {
            if (!file_exists($sourceFile)) {
                return false;
            }

            if (copy($sourceFile, $targetPath)) {
                return true;
            } else {
                return false;
            }

        } catch (Exception $e) {
            return false;
        }
    }

    public function runBatchFile($site_name) 
    {
        $url = 'http://127.0.0.1:3333/docker/init?site_name='.$site_name;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            echo 'Error: ' . $error;
            
            curl_close($ch);
            return false;
        } 

        curl_close($ch);
        return true;
    }

    public function getContainerStatus($site_name)
    {
        $url = 'http://127.0.0.1:3333/docker/containers?filters={"name":["ezweb"],"status":["running","exited"]}';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $resp = json_decode($response, true);

        if ($resp['data'] == "") {
            $error = curl_error($ch);
            echo 'Error: ' . $error;
            
            curl_close($ch);
            return false;
        } 
        curl_close($ch);
        return $resp['data'];
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
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
        $form->select('state', '狀態')->options([ // creating/burn-up/running/stop/error/shutdown
            'creating'  => '檔案建立中',
            'burn-up'   => '服務啟動中',
            'running'   => '服務運行中',
            'stop'      => '服務暫停',
            'error'     => '服務異常',
            'shutdown'  => '服務關閉',
        ]);

        $form->saving(function (Form $form) {
            $siteName   = $form->site_name;
            $sitePort   = $form->site_port;
            $dbPort     = $form->db_port;
            $templateId = $form->template_id;
            $zipFile    = 'wp-ezweb.zip';
            $newFolderName = $siteName; // 新目錄名稱

            if ( $form->state == 'creating' ) {

                $resp = false;
                $resp = $this->checkDirectoryExists($newFolderName);
                // if( $resp == false )
                //     $resp = $this->checkSiteNameExists($newFolderName);
                if( $resp == false )
                    $resp = $this->unzipAndRename($zipFile, $newFolderName);
                if( $resp == true )
                    $resp = $this->copyEnv($siteName, $sitePort, $dbPort);
                if( $resp == true )
                    $resp = $this->copyWpAutoConfig($siteName, $templateId);
                if( $resp == true )
                    $resp = $this->copyMakeFile($siteName, $templateId);
                if( $resp == true )
                    $resp = $this->copyTemplate($siteName, $templateId);
                if( $resp == true )
                    $resp = $this->runBatchFile($siteName);
                if( $resp == true )
                    $form->state = "burn-up";
                else
                    $form->state = "error";

            } else if ( $form->state == 'burn-up' ) { 
                $resp = false;
                $resp = $this->checkDirectoryExists($newFolderName);
                if( $resp == true )
                    $resp == $this->getContainerStatus("ezweb");
                if($resp != "running");
            }
        });

        return $form;
    }
}
