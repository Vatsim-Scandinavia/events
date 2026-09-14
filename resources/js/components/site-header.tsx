import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Plane } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuList,
    NavigationMenuItem,
    NavigationMenuLink,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { home, login } from '@/routes';

export function SiteHeader() {
    const { auth, name } = usePage().props;

    return (
        <header className="site-header">
            <Link
                href={home()}
                className="site-brand"
                aria-label={`${name} home`}
            >
                <Plane aria-hidden="true" className="size-7" />
                <span>{name}</span>
            </Link>
            <NavigationMenu
                aria-label="Main navigation"
                className="site-navigation"
                viewport={false}
            >
                <NavigationMenuList>
                    <NavigationMenuItem>
                        <NavigationMenuLink
                            asChild
                            className={navigationMenuTriggerStyle()}
                        >
                            <a href={`${home.url()}#upcoming-events`}>
                                Upcoming events
                            </a>
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                        <NavigationMenuLink
                            asChild
                            className={navigationMenuTriggerStyle()}
                        >
                            <a href={`${home.url()}#about`}>About</a>
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>
            <div className="site-account">
                {auth.user ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                className="max-w-48"
                                aria-label="Open account menu"
                            >
                                <span className="truncate">
                                    {auth.user.name}
                                </span>
                                <ChevronDown
                                    aria-hidden="true"
                                    data-icon="inline-end"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-64">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : (
                    <Button asChild variant="outline">
                        <Link href={login()}>Sign in</Link>
                    </Button>
                )}
            </div>
        </header>
    );
}
