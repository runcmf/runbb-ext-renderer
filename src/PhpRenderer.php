<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BBRenderer;

class PhpRenderer extends View
{
    protected $directories = [];
    protected $alias;

    /**
     * PhpRenderer constructor.
     */
    public function __construct()
    {
        parent::__construct();

        defined('INBB') || define('INBB', true);
    }

    /**
     * @param string $dir
     * @param string $alias
     * @return $this
     */
    public function addTemplatesDirectory($dir = '', $alias = 'forum')
    {
        $directories = (array) $dir;
        foreach ($directories as $key => $tpl_dir) {
            if (is_dir($tpl_dir)) {
                // only one path by alias
                $this->directories[$alias] = rtrim((string) $tpl_dir, DIRECTORY_SEPARATOR);
            }
        }
        return $this;
    }

    public function getTemplate($file)
    {
        foreach ($this->directories as $dir) {
            $pathname = realpath($dir . DIRECTORY_SEPARATOR . ltrim($file . '.php', DIRECTORY_SEPARATOR));
            if (is_file($pathname)) {
                return (string) $pathname;
            }
        }
        throw new \RunBB\Exception\RunBBException(
            "View cannot get template `$file` from stack because the template does not exist"
        );
    }

    /**
     * @param string $template
     * @param bool $nested
     * @return array|string
     * @throws \Exception
     * @throws \Throwable
     */
    public function render($template = '', $nested = true)
    {
        $data = $this->getPageData();
        $data = Container::get('hooks')->fire('view.alter_data', $data);
        list($namespace, $shortname) = $this->parseName($template);
//dump($namespace);
        try {
            ob_start();
            extract($data);
            $startTime = microtime(true);
            if ($nested) {
                include $this->getTemplate('header');
            }
            include $this->getTemplate($shortname);
            if ($nested) {
                include $this->getTemplate('footer');
            }
            $this->renderTime = microtime(true) - $startTime;
            $output = ob_get_clean();
        } catch(\Throwable $e) { // PHP 7+
            ob_end_clean();
            throw $e;
        } catch(\Exception $e) { // PHP < 7
            ob_end_clean();
            throw $e;
        }

        return $output;
    }

    /**
     * @param string $template
     * @param bool $nested
     * @return mixed
     */
    public function display($template = '', $nested = true)
    {
        Response::getBody()->write(
            $this->render($template, $nested)
        );
        // Check Tracy installed
        if(function_exists('bdump')) {
            bdump('PHP render time: '.$this->renderTime);
        }
        return Container::get('response');
    }
}