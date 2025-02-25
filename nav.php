<?php
// nav.php
function getNavigation($current_page, $user_role) {
    $nav_items = [
        'dashboard' => ['title' => 'Dashboard', 'url' => 'dashboard.php', 'roles' => ['admin', 'accountant', 'client']],
        'invoices' => ['title' => 'Invoices', 'url' => 'invoice_management.php', 'roles' => ['admin', 'accountant']],
        'clients' => ['title' => 'Clients', 'url' => 'client_management.php', 'roles' => ['admin', 'accountant']],
        'reports' => ['title' => 'Reports', 'url' => 'reports.php', 'roles' => ['admin', 'accountant']],
        'users' => ['title' => 'Users', 'url' => 'user_management.php', 'roles' => ['admin']],
    ];

    $output = '<nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <img class="h-8 w-auto" src="https://tailwindui.com/img/logos/workflow-mark-indigo-600.svg" alt="Workflow">
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">';

    foreach ($nav_items as $key => $item) {
        if (in_array($user_role, $item['roles'])) {
            $active_class = ($current_page === $key) ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700';
            $output .= '<a href="' . $item['url'] . '" class="' . $active_class . ' inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                ' . $item['title'] . '
            </a>';
        }
    }

    $output .= '</div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-white rounded-full flex text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="sr-only">Open user menu</span>
                                <img class="h-8 w-8 rounded-full" src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="">
                            </button>
                        </div>
                    </div>
                </div>
                <div class="-mr-2 flex items-center sm:hidden">
                    <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500" aria-controls="mobile-menu" aria-expanded="false" id="mobile-menu-button">
                        <span class="sr-only">Open main menu</span>
                        <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu, show/hide based on menu state. -->
        <div class="sm:hidden hidden" id="mobile-menu">
            <div class="pt-2 pb-3 space-y-1">';

    foreach ($nav_items as $key => $item) {
        if (in_array($user_role, $item['roles'])) {
            $active_class = ($current_page === $key) ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700';
            $output .= '<a href="' . $item['url'] . '" class="' . $active_class . ' block pl-3 pr-4 py-2 border-l-4 text-base font-medium">' . $item['title'] . '</a>';
        }
    }

    $output .= '</div>
        </div>
    </nav>';

    return $output;
}