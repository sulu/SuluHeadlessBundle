import request from '../utils/request';

class Requester {
    requestErrorHandler = null;
    defaultOptions = {
        // Copied from sulu/sulu. Will be merged with the default options of the utils/request helper.
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    interceptRequestResponse = (response) => {
        if (!response.ok) {
            return Promise.reject(response);
        }

        if (response.status === 204) {
            return Promise.resolve();
        }

        return response.json();
    };

    interceptRequestError = (error) => {
        if (this.requestErrorHandler) {
            return this.requestErrorHandler(error);
        } else {
            throw error;
        }
    };

    setRequestErrorHandler = (requestErrorHandler) => {
        this.requestErrorHandler = requestErrorHandler;
    };

    setDefaultOptions = (defaultOptions) => {
        this.defaultOptions = defaultOptions;
    };

    get(url, options) {
        const requestOptions = {
            ...this.defaultOptions,
            ...options,
            method: 'GET',
        };

        return request(url, requestOptions)
            .then(this.interceptRequestResponse)
            .catch(this.interceptRequestError);
    }

    post(url, data, options) {
        const requestOptions = {
            ...this.defaultOptions,
            ...options,
            method: 'POST',
            body: JSON.stringify(data),
        };

        return request(url, requestOptions)
            .then(this.interceptRequestResponse)
            .catch(this.interceptRequestError);
    }

    put(url, data, options) {
        const requestOptions = {
            ...this.defaultOptions,
            ...options,
            method: 'PUT',
            body: JSON.stringify(data),
        };

        return request(url, requestOptions)
            .then(this.interceptRequestResponse)
            .catch(this.interceptRequestError);
    }

    patch(url, data, options) {
        const requestOptions = {
            ...this.defaultOptions,
            ...options,
            method: 'PATCH',
            body: JSON.stringify(data),
        };

        return request(url, requestOptions)
            .then(this.interceptRequestResponse)
            .catch(this.interceptRequestError);
    }

    delete(url, options) {
        const requestOptions = {
            ...this.defaultOptions,
            ...options,
            method: 'DELETE',
        };

        return request(url, requestOptions)
            .then(this.interceptRequestResponse)
            .catch(this.interceptRequestError);
    }
}

export default new Requester();
