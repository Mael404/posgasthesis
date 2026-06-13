<div
    x-show="$store.sidebar.isMobileOpen"
    x-transition.opacity
    @click="$store.sidebar.setMobileOpen(false)"
    class="fixed inset-0 z-[99990] bg-gray-900/50 xl:hidden"
    style="display: none;"
></div>
