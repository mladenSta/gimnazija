<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    FileText,
    FolderTree,
    GalleryHorizontalEnd,
    GraduationCap,
    Images,
    LayoutGrid,
    ListTree,
    Newspaper,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as alumniIndex } from '@/routes/admin/alumni';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as eventsIndex } from '@/routes/admin/events';
import { index as galleriesIndex } from '@/routes/admin/galleries';
import { index as mediaIndex } from '@/routes/admin/media';
import { edit as menuEdit } from '@/routes/admin/menu';
import { index as pagesIndex } from '@/routes/admin/pages';
import { index as postsIndex } from '@/routes/admin/posts';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const page = usePage();

const allNavItems: NavItem[] = [
    {
        title: 'Kontrolna tabla',
        href: dashboard(),
        icon: LayoutGrid,
        exact: true,
    },
    { title: 'Objave', href: postsIndex(), icon: Newspaper },
    { title: 'Stranice', href: pagesIndex(), icon: FileText },
    { title: 'Mediji', href: mediaIndex(), icon: Images },
    { title: 'Meni', href: menuEdit(), icon: ListTree },
    { title: 'Galerije', href: galleriesIndex(), icon: GalleryHorizontalEnd },
    { title: 'Najave', href: eventsIndex(), icon: CalendarDays },
    {
        title: 'Bivši učenici',
        href: alumniIndex(),
        icon: GraduationCap,
        roles: ['admin'],
    },
    {
        title: 'Korisnici',
        href: usersIndex(),
        icon: Users,
        roles: ['admin'],
    },
    {
        title: 'Kategorije',
        href: categoriesIndex(),
        icon: FolderTree,
        roles: ['admin'],
    },
];

const mainNavItems = computed(() =>
    allNavItems.filter(
        (item) => !item.roles || item.roles.includes(page.props.auth.user.role),
    ),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
