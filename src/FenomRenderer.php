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

use Fenom;

class FenomRenderer extends View
{
    protected $directories = [];
    private $fenom;

    public function __construct(array $items = [])
    {
        parent::__construct($items);

        $this->fenom = new Fenom(new Fenom\Provider(ForumEnv::get('FORUM_ROOT') . 'View'));
        $this->fenom->setCompileDir(ForumEnv::get('FORUM_CACHE_DIR') . 'fenom');
        $this->fenom->setOptions([
            'disable_accessor' => false,
            'disable_methods' => false,
            'disable_native_funcs' => true,
            'disable_cache' => false,
            'force_compile' => false,
            'auto_reload' => true,
            'force_include' => false,
            'auto_escape' => false,
            'force_verify' => false,
            'auto_trim' => false,
            'disable_php_calls' => true,
            'disable_statics' => false,
            'strip' => false
        ]);
        $this->addFunctions();
    }

    public function addTemplatesDirectory($dir = '', $alias = 'forum')
    {
        //FIXME test with extensions!!!
        $this->fenom->addProvider($alias, new Fenom\Provider($dir));
//        $directories = (array) $dir;
//        foreach ($directories as $key => $tpl_dir) {
//            if (is_dir($tpl_dir)) {
//                // only one path by alias
//                $this->directories[$alias] = rtrim((string) $tpl_dir, DIRECTORY_SEPARATOR);
//            }
//        }
//        return $this;
    }

    public function render($template = '', $nested = true)
    {
        $data = $this->getPageData();
        $data['nested'] = $nested;
        $data = Container::get('hooks')->fire('view.alter_data', $data);

        list($namespace, $shortname) = $this->parseName($template);

        $startTime = microtime(true);
        $res = $this->fenom->fetch($shortname . '.html.tpl', $data);
        $this->renderTime = microtime(true) - $startTime;
        return $res;
    }

    public function display($template = '', $nested = true)
    {
        Response::getBody()->write(
            $this->render($template, $nested)
        );
        // Check Tracy installed
        if(function_exists('bdump')) {
            bdump('Fenom render time: '.$this->renderTime);
        }
        return Container::get('response');
    }

    private function addFunctions()
    {
        //$fenom->addFunction("some_function", function (array $params) { /* ... */ });

        //$fenom->addAccessorSmart("storage", "App::getInstance()->storage", Fenom::ACCESSOR_VAR);
        //{set $st = $.storage.di.stamp} {* $st = App::getInstance()->storage['di']['stamp'] *}

        //$fenom->addAccessorSmart("di", "App::getInstance()->di->get", Fenom::ACCESSOR_CALL);
        //{set $st = $.di("stamp")} {* $st = App::getInstance()->di->get("stamp") *}
        $this->fenom->addAccessorSmart('fireHook', "Container::get('hooks')->fire", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('formatTime', "Container::get('utils')->timeFormat", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('formatNumber', "Container::get('utils')->numberFormat", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('settings', "ForumSettings::get", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('trans', "__", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('transd', "d__", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('userGet', "User::getVar", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('getEnv', "ForumEnv::get", Fenom::ACCESSOR_CALL);
        $this->fenom->addAccessorSmart('pathFor', "Router::pathFor", Fenom::ACCESSOR_CALL);
    }
}
