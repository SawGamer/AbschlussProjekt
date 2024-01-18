export const store = {
    _JWT: null,

    setJWT(data) {
        this._JWT = data;
    },

    getJWT() {
        return this._JWT;
    }
};

export const fetchAndSetJwtToken = async () => {
    try {
        const response = await fetch('../authenticate');
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();
        sessionStorage.setItem('jwt', data['jwt']);
        store.setJWT(data['jwt']);
        console.log(data['jwt'])
    } catch (error) {
        console.error("Error fetching data:", error);
        store.setJWT(sessionStorage.getItem('jwt'));

    }
};
