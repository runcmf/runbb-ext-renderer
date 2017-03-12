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

use Slim\Collection;
use RunBB\Interfaces\ViewInterface;
use RunBB\Exception\RunBBException;
use RunBB\Core\Utils;
use RunBB\Core\Random;

abstract class View extends Collection implements ViewInterface
{
    const MAIN_NAMESPACE = '__main__';
    public $renderTime = 0;

    protected $templates = [];
    protected $assets = [];
    protected $validation = [
        'page_number' => 'intval',
        'active_page' => 'strval',
        'is_indexed' => 'boolval',
        'admin_console' => 'boolval',
        'has_reports' => 'boolval',
        'paging_links' => 'strval',
        'footer_style' => 'strval',
        'fid' => 'intval',
        'pid' => 'intval',
        'tid' => 'intval'
    ];

    /********************************************************************************
     * Getters and setters
     *******************************************************************************/

    /**
     * Initialise style, load assets for given style
     * @param $style
     * @throws RunBBException
     */
    public function setStyle($style)
    {
        $dir = ForumEnv::get('WEB_ROOT').'themes/'.$style.'/';
        if (!is_dir($dir)) {
            throw new RunBBException('The style '.$style.' doesn\'t exist');
        }

        if (is_file($dir . 'bootstrap.php')) {
            $vars = include_once $dir . 'bootstrap.php';
            // file exist but return nothing
            if (!is_array($vars)) {
                $vars = [];
            }
            foreach ($vars as $key => $assets) {
                if ($key === 'jsraw' || !in_array($key, ['js', 'jshead', 'css'])) {
                    continue;
                }
                foreach ($assets as $asset) {
                    $params = ($key === 'css') ? ['type' => 'text/css', 'rel' => 'stylesheet'] : (
                    ($key === 'js' || $key === 'jshead') ? ['type' => 'text/javascript'] : []
                    );
                    $this->addAsset($key, $asset, $params);
                }
            }
            $this->set('jsraw', isset($vars['jsraw']) ? $vars['jsraw'] : '');
        }

        if (isset($vars['themeTemplates']) && $vars['themeTemplates'] == true) {
            $templatesDir = ForumEnv::get('WEB_ROOT').'themes/'.$style.'/view';
        } else {
            $templatesDir = ForumEnv::get('FORUM_ROOT') . 'View/';
        }

        $this->set('style', (string) $style);

        $this->addTemplatesDirectory($templatesDir);
    }

    public function setPageInfo(array $data)
    {
        foreach ($data as $key => $value) {
            list($key, $value) = $this->validate($key, $value);
            $this->set($key, $value);
        }
        return $this;
    }

    protected function validate($key, $value)
    {
        $key = (string) $key;
        if (isset($this->validation[$key])) {
            if (function_exists($this->validation[$key])) {
                $value = $this->validation[$key]($value);
            }
        }
        return [$key, $value];
    }

    public function addAsset($type, $asset, $params = [])
    {
        $type = (string) $type;
        if (!in_array($type, ['js', 'jshead', 'css', 'feed', 'canonical', 'prev', 'next'])) {
            throw new RunBBException('Invalid asset type : ' . $type);
        }
        if (in_array($type, ['js', 'jshead', 'css']) && !is_file(ForumEnv::get('WEB_ROOT').$asset)) {
            throw new RunBBException('The asset file ' . $asset . ' does not exist');
        }

        $params = array_merge(static::getDefaultParams($type), $params);
        if (isset($params['title'])) {
            $params['title'] = Utils::escape($params['title']);
        }
        $this->assets[$type][] = [
            'file' => (string) $asset,
            'params' => $params
        ];
    }

//    public function getAssets()
//    {
//        return $this->assets;
//    }

//    public function addTemplate($tpl, $priority = 10)
//    {
//        $tpl = (array) $tpl;
//        foreach ($tpl as $key => $tpl_file) {
//            $this->templates[(int) $priority][] = (string) $tpl_file;
//        }
//        return $this;
//    }

//    public function getTemplates()
//    {
//        $output = [];
//        if (count($this->templates) > 1) {
//            ksort($this->templates);
//        }
//        foreach ($this->templates as $priority) {
//            if (!empty($priority)) {
//                foreach ($priority as $tpl) {
//                    $output[] = $tpl;
//                }
//            }
//        }
//        return $output;
//    }

    public function addMessage($msg, $type = 'info')
    {
        if (Container::get('flash')) {
            if (in_array($type, ['info', 'error', 'warning', 'success'])) {
                Container::get('flash')->addMessage($type, (string) $msg);
            }
        }
    }

    public function getPageData()
    {
        // Check if config file exists to avoid error when installing forum
        if (!Container::get('cache')->isCached('quickjump') && is_file(ForumEnv::get('FORUM_CONFIG_FILE'))) {
            Container::get('cache')->store('quickjump', \RunBB\Model\Cache::getQuickjump());
        }

        $title = Container::get('forum_settings') ? ForumSettings::get('o_board_title') : 'RunBB';
        $style = $this->get('style');
        if (file_exists(ForumEnv::get('WEB_ROOT').'themes/'.$style.'/base_admin.css')) {
            $admStyle = '<link rel="stylesheet" type="text/css" href="'.
                Url::baseStatic().'/themes/'.$style.'/base_admin.css" />';
        } else {
            $admStyle = '<link rel="stylesheet" type="text/css" href="'.
                Url::baseStatic().'/imports/base_admin.css" />';
        }

        $data = [
            'title' => Utils::escape($title),
            'page_number' => null,
            'active_page' => 'index',
            'focus_element' => null,
            'is_indexed' => true,
            'admin_console' => false,
            'page_head' => null,
            'paging_links' => null,
            'required_fields' => null,
            'footer_style' => null,
            'quickjump' => Container::get('cache')->retrieve('quickjump'),
            'fid' => null,
            'pid' => null,
            'tid' => null,
            'assets' => $this->assets,
            'languagesQSelect' => Lang::getList(),
            'stylesQSelect' => \RunBB\Core\Lister::getStyles(),
            'currentPage' => Url::current(),
            'flashMessages' => Container::get('flash')->getMessages(),
            'style' => $style,
            'admStyle' => $admStyle
        ];

        if (User::get() !== null && User::get() !== false) {
            if (User::get()->is_admmod) {
                $data['has_reports'] = \RunBB\Model\Admin\Reports::hasReports();
            }
            // check db configured
            if (DB::getConfig()['username'] !== null && User::get()->g_id == ForumEnv::get('FEATHER_GUEST')) {
                // guest user. for modal. load reg data from Register.php
                Lang::load('login');
                Lang::load('register');
                Lang::load('prof_reg');
                Lang::load('antispam');

                // FIXME rebuild
                // Antispam feature
                $lang_antispam_questions = require ForumEnv::get('FORUM_ROOT') .
                    'lang/' . User::get()->language . '/antispam.php';
                $index_questions = rand(0, count($lang_antispam_questions) - 1);
                $data['index_questions'] = $index_questions;
                $data['question'] = array_keys($lang_antispam_questions);
                $data['qencoded'] = md5(array_keys($lang_antispam_questions)[$index_questions]);
                $data['logOutLink'] = Router::pathFor(
                    'logout',
                    ['token' => Random::hash(User::get()->id.Random::hash(Utils::getIp()))]
                );
            }

            if (ForumEnv::get('FEATHER_SHOW_INFO')) {
                $data['exec_info'] = \RunBB\Model\Debug::getInfo();
            }
        }
        $data = array_merge($data, $this->all());

        $data['pageTitle'] = Utils::generatePageTitle($data['title'], $data['page_number']);
        $data['navlinks'] = $this->buildNavLinks($data['active_page']);

        return $data;
    }

    protected static function getDefaultParams($type)
    {
        switch ($type) {
            case 'js':
                return ['type' => 'text/javascript'];
            case 'jshead':
                return ['type' => 'text/javascript'];
            case 'css':
                return ['rel' => 'stylesheet', 'type' => 'text/css'];
            case 'feed':
                return ['rel' => 'alternate', 'type' => 'application/atom+xml'];
            case 'canonical':
                return ['rel' => 'canonical'];
            case 'prev':
                return ['rel' => 'prev'];
            case 'next':
                return ['rel' => 'next'];
            default:
                return [];
        }
    }

    protected function buildNavLinks($active_page = '')
    {
        $navlinks = [];
        // user not initialized, possible we in install
        if (User::get() === null || User::get() === false) {
            return $navlinks;
        }

        $navlinks[] = [
            'id' => 'navindex',
            'active' => ($active_page == 'index') ? ' class="isactive"' : '',
            'href' => Router::pathFor('home'),
            'text' => __('Index')
        ];

        if (User::get()->g_read_board == '1' && User::get()->g_view_users == '1') {
            $navlinks[] = [
                'id' => 'navuserlist',
                'active' => ($active_page == 'userlist') ? ' class="isactive"' : '',
                'href' => Router::pathFor('userList'),
                'text' => __('User list')
            ];
        }

        if (ForumSettings::get('o_rules') == '1' && (!User::get()->is_guest
                || User::get()->g_read_board == '1'
                || ForumSettings::get('o_regs_allow') == '1')
        ) {
            $navlinks[] = [
                'id' => 'navrules',
                'active' => ($active_page == 'rules') ? ' class="isactive"' : '',
                'href' => Router::pathFor('rules'),
                'text' => __('Rules')
            ];
        }

        if (User::get()->g_read_board == '1' && User::get()->g_search == '1') {
            $navlinks[] = [
                'id' => 'navsearch',
                'active' => ($active_page == 'search') ? ' class="isactive"' : '',
                'href' => Router::pathFor('search'),
                'text' => __('Search')
            ];
        }

        // Are there any additional navlinks we should insert into the array before imploding it?
        $hooksLinks = Container::get('hooks')->fire('view.header.navlinks', []);
        $extraLinks = ForumSettings::get('o_additional_navlinks')."\n".implode("\n", $hooksLinks);
        if (User::get()->g_read_board == '1' && ($extraLinks != '')) {
            if (preg_match_all('%([0-9]+)\s*=\s*(.*?)\n%s', $extraLinks."\n", $results)) {
                // Insert any additional links into the $links array (at the correct index)
                $num_links = count($results[1]);
                for ($i = 0; $i < $num_links; ++$i) {
                    array_splice(
                        $navlinks,
                        $results[1][$i],
                        0,
                        ['<li id="navextra'.($i + 1).'"'.
                            (($active_page == 'navextra'.($i + 1)) ? ' class="isactive"' : '').'>'.
                            $results[2][$i].'</li>']
                    );
                }
            }
        }

        return $navlinks;
    }

    /**
     * Twig function
     *
     * @param $name
     * @param string $default
     * @return array
     * @throws \RunBB\Exception\RunBBException
     */
    protected function parseName($name, $default = self::MAIN_NAMESPACE)
    {
        if (isset($name[0]) && '@' == $name[0]) {
            if (false === $pos = strpos($name, '/')) {
                throw new \RunBB\Exception\RunBBException(
                    sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name)
                );
            }
            $namespace = substr($name, 1, $pos - 1);
            $shortname = substr($name, $pos + 1);

            return array($namespace, $shortname);
        }

        return array($default, $name);
    }
}
