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
    /**
     * @var string
     */
    protected $templatePath;

    /**
     * @param string $dir
     * @param string $alias
     * @return $this
     */
    public function addTemplatesDirectory($dir = '', $alias = 'forum')
    {
        $this->templatePath = rtrim($dir, '/\\') . '/';
        return $this;
    }

    /**
     * @param bool $nested
     * @return mixed
     * @throws \Exception
     * @throws \Throwable
     */
    public function display($nested = true)
    {
        $data = [
            'nested' => $nested
        ];
        $data = array_merge($this->getDefaultPageInfo(), $this->data->all(), (array) $data);

        $data = Container::get('hooks')->fire('view.alter_data', $data);

        $templates = $this->getTemplates();
        $tpl = trim(array_pop($templates));// get last in array

        try {
            ob_start();
            extract($data);
            include $this->templatePath . $tpl;
            $output = ob_get_clean();
        } catch(\Throwable $e) { // PHP 7+
            ob_end_clean();
            throw $e;
        } catch(\Exception $e) { // PHP < 7
            ob_end_clean();
            throw $e;
        }

        Response::getBody()->write($output);
        return Container::get('response');
    }
}