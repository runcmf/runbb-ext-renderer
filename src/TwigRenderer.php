<?php

/**
* Copyright (C) 2015-2016 FeatherBB
* based on code by (C) 2008-2015 FluxBB
* and Rickard Andersson (C) 2002-2008 PunBB
* License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
*/

namespace BBRenderer;

class TwigRenderer extends View
{
    private $twig;
    public $loader = null;

    /**
     * TwigRenderer constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->loader = new \Twig_Loader_Filesystem();
        $this->twig = new \Twig_Environment($this->loader, [
            'cache' => ForumEnv::get('FORUM_CACHE_DIR') . 'twig',
            'debug' => true,
        ]);
        // load extensions
        if (ForumEnv::get('FEATHER_DEBUG')) {
            $this->twig->addExtension(new \Twig_Extension_Profiler(
                Container::get('twig_profile')
            ));
            $this->twig->addExtension(new \Twig_Extension_Debug());
        }
        $this->twig->addExtension(new TwigExtension());

        return $this;
    }

    /**
     * @param string $dir
     * @param string $alias
     * @return $this
     */
    public function addTemplatesDirectory($dir = '', $alias = 'forum')
    {
        $this->loader->addPath($dir, $alias);
        return $this;
    }

    /**
     * @param bool $nested
     * @return mixed
     */
    public function display($nested = true)
    {
        $data = [
            'nested' => $nested
        ];
        $data = array_merge($this->getDefaultPageInfo(), $this->all(), (array) $data);
        $data = Container::get('hooks')->fire('view.alter_data', $data);

        // TODO set template to display method?
        $templates = $this->getTemplates();
        $tpl = trim(array_pop($templates));// get last in array


        Response::getBody()->write(
            $this->twig->render($tpl. '.html.twig', $data)
        );
        return Container::get('response');
    }
}
