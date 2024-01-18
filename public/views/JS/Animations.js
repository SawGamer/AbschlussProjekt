function fadeOut(element) {
    let op = 2; 
    let T = setInterval(function () {
        if (op <= 0.3){
            clearInterval(T);
            element.style.display = 'none';

        }
        element.style.opacity = op;
        element.style.filter = 'alpha(opacity=' + op * 100 + ")";
        op -= op * 0.05;
    }, 30);
}
function resetAndShowElement(element) {
    element.style.display = 'block';
}

function animateElement(element,classname) {
    element.classList.add(classname);
    console.log("whtis")

    element.addEventListener('animationend', function() {
        element.style.display = 'none'; 
        element.style.opacity = 1;      
        element.classList.remove(classname); 
    }, { once: true }); 
}









export{fadeOut,resetAndShowElement,animateElement}