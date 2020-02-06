import { createBrowserHistory } from 'history';
import loglevel from 'loglevel';
import { computed, observable } from 'mobx';
import staticRouteRegistry from '../registries/staticRouteRegistry';
import viewDataStore from '../stores/viewDataStore';

class Router {
    history;
    viewDataErrorHandler;

    @observable location;

    constructor() {
        this.history = createBrowserHistory();

        // Try to gather view-data from window object to prevent unnecessary request on initial page load
        if (window.SULU_HEADLESS_VIEW_DATA) {
            loglevel.info('Loaded view data from window object.', window.SULU_HEADLESS_VIEW_DATA);

            viewDataStore.setData(window.SULU_HEADLESS_VIEW_DATA);
            this.location = this.history.location;
            delete window.SULU_HEADLESS_VIEW_DATA;
        } else {
            this.handleLocationChange(this.history.location, 'initialization');
        }

        // Listen for future location updates
        this.history.listen(this.handleLocationChange);
    }

    setViewDataErrorHandler = (viewDataErrorHandler) => {
        this.viewDataErrorHandler = viewDataErrorHandler;
    };

    handleLocationChange = (location, action) => {
        loglevel.info('URL was changed.', location, action);

        // Set view-data directly from static-route if a static route is registered for the pathname
        if (staticRouteRegistry.has(location.pathname)) {
            viewDataStore.setData(staticRouteRegistry.get(location.pathname));
            this.location = location;

            return;
        }

        // If no matching static-route is registered, try to load the view data from the server
        return viewDataStore.loadData(location.pathname).then(() => {
            this.location = location;
        }).catch((error) => {
            if (this.viewDataErrorHandler) {
                return this.viewDataErrorHandler(error);
            } else {
                loglevel.error(
                    'Error during location change. Did not find view-data for pathname "' + location.pathname + '".',
                    error
                );
            }
        });
    };

    @computed get pathname() {
        return this.location.pathname;
    }

    @computed get search() {
        return this.location.search;
    }

    @computed get hash() {
        return this.location.hash;
    }

    reload = () => {
        this.handleLocationChange(this.location, 'reload');
    };

    assign = (pathname, search) => {
        this.history.push({
            pathname, search,
        });
    };

    replace = (pathname, search) => {
        this.history.replace({
            pathname, search,
        });
    };
}

export default new Router();
