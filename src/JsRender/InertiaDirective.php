<?php

namespace Maharlika\JsRender;

class InertiaDirective
{
    public function render(): string
    {
        $page = request()->attributes->get('inertia.page');
        
        if (!$page) {
            // Get from view data
            $page = view()->shared('page') ?? [];
        }
        
        $json = htmlspecialchars(json_encode($page), ENT_QUOTES, 'UTF-8', true);
        
        return sprintf('<div id="app" data-page="%s"></div>', $json);
    }
    
    public function renderHead(): string
    {
        return '<meta name="inertia" content="true">';
    }
}
