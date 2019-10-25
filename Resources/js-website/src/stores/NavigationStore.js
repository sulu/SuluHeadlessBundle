import { action, computed, observable } from 'mobx';
import loglevel from 'loglevel';
import requester from '../services/requester';
import buildQueryString from '../utils/buildQueryString';

export default class NavigationStore {
    context;
    queryOptions;

    @observable data = null;
    @observable loading = false;
    @observable error = null;

    constructor(context, depth, flat, excerpt, parentUuid) {
        this.context = context;
        this.queryOptions = {
            depth,
            flat,
            excerpt,
            uuid: parentUuid,
        };
        const queryString = buildQueryString(this.queryOptions);

        requester.get(window.SULU_HEADLESS_API_ENDPOINT + '/navigations/' + this.context + '?' + queryString, {})
            .then(action((data) => {
                this.loading = false;
                this.data = data;
            })).catch(action((error) => {
                loglevel.error('Error while loading navigation for context "' + this.context + '".', error);
                this.loading = false;
                this.error = error;
            }));
    }

    @computed get items() {
        return this.data ? this.data['_embedded'].items : [];
    }
}

