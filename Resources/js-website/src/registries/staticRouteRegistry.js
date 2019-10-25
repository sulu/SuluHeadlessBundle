class StaticRouteRegistry {
    routes = {};

    clear() {
        this.routes = {};
    }

    has(pathname) {
        return pathname in this.routes;
    }

    add(pathname, viewData) {
        if (this.has(pathname)) {
            throw new Error('The pathname "' + pathname + '" has already been used for another route');
        }

        this.routes[pathname] = viewData;
    }

    get(pathname) {
        if (this.has(pathname)) {
            return this.routes[pathname];
        }

        throw new Error('There is no static route for the pathname "' + pathname + '" registered');
    }
}

export default new StaticRouteRegistry();
