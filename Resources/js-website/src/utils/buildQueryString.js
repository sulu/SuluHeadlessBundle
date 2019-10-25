export default (parameterObject) => {
    return Object.keys(parameterObject)
        .filter(key => parameterObject[key])
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(parameterObject[key]))
        .join('&');
};
