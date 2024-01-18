// JavaScript for the Admin Dashboard page

document.addEventListener('DOMContentLoaded', function () {
    fetchAllUsers();
});

function fetchAllUsers() {
    fetch("api/admin/get-all-users")
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            console.log(response)
            return response.json();
        })
        .then(users => {
            generateUserList(users);

            console.log(users)
        })
        .catch(error => {
            console.error("Error fetching users:", error);
        });
}


function generateUserList(users) {
    const userList = document.getElementById("userList");
    userList.innerHTML = "";

    const usertag = document.getElementById("usernameweb")
    const user = users.find(event => event.first_name === "Admin");
    usertag.innerHTML = user ? user.last_name : "DU NIX";


    users.forEach(user => {

        const listItem = document.createElement("li");
        listItem.textContent = user.first_name;
        listItem.addEventListener("click", function () {
            redirectToUserPage(user.id); 
        });

        userList.appendChild(listItem);
    });
}

function redirectToUserPage(userId) {
    window.location.href = `../public/pages/monthlyhours.php?id=${userId}`;
}
