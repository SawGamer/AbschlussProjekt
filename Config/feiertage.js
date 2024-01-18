// events.js

let eventsList 
fetch('../Config/feiertage.json')
    .then(response => response.json())
    .then(data => {
        console.log(data)
        eventsList = data})
    .catch(error => console.error('Error:', error));

let Urlaub = []



function isfeiertag(date) {
    return eventsList.some(event => new Date(event.date).getTime() === date.getTime());
}
function isUrlaub(date) {
    return Urlaub.some(event => new Date(event.date) === date);
}
function addUrlaub(date, name) {
    Urlaub.push({ "date": date, "fname": name });
}
export { isfeiertag, isUrlaub, addUrlaub };


