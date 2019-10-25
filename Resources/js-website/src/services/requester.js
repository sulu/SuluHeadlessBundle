import request from '../utils/request';

const defaultOptions = {
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest', // Copied from sulu/sulu
    },
};

class Requester {
    handleResponse(response) {
        if (!response.ok) {
            return Promise.reject(response);
        }

        if (response.status !== 204) {
            return response.json();
        }

        return Promise.resolve();
    }

    get(url, options) {
        return request(url, {
            ...defaultOptions,
            ...options,
            method: 'GET',
        }).then(this.handleResponse);
    }

    post(url, data, options) {
        return request(url, {
            ...defaultOptions,
            ...options,
            method: 'POST',
            body: JSON.stringify(data),
        }).then(this.handleResponse);
    }

    put(url, data, options) {
        return request(url, {
            ...defaultOptions,
            ...options,
            method: 'PUT',
            body: JSON.stringify(data),
        }).then(this.handleResponse);
    }

    patch(url, data, options) {
        return request(url, {
            ...defaultOptions,
            ...options,
            method: 'PATCH',
            body: JSON.stringify(data),
        }).then(this.handleResponse);
    }

    delete(url, options) {
        return request(url, {
            ...defaultOptions,
            ...options,
            method: 'DELETE',
        }).then(this.handleResponse);
    }
}

export default new Requester();
