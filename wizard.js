let step = 0;
let data = {};

const steps = [
{ id:"f1", q:"Haus oder Wohnung?", type:"radio", options:["Haus","Wohnung"] },
{ id:"f2", q:"Vermieten oder Verkaufen?", type:"radio", options:["Vermieten","Verkaufen"] },
{ id:"f3", q:"Land?", type:"text" },
{ id:"f6", q:"Adresse?", type:"text" },
{ id:"f8", q:"Bauweise?", type:"text" },
{ id:"f12", q:"Baujahr?", type:"number" },
{ id:"f17", q:"Zimmeranzahl?", type:"number" },
{ id:"f19", q:"Haustiere erlaubt?", type:"radio", options:["Ja","Nein"] },
{ id:"f20", q:"Lift vorhanden?", type:"radio", options:["Ja","Nein"] },
{ id:"f22", q:"Rollstuhlgerecht?", type:"radio", options:["Ja","Nein"] },
{ id:"f24", q:"Bilder hochladen", type:"file" },
{ id:"f33", q:"Titel des Inserats?", type:"text" },
{ id:"f34", q:"Beschreibung?", type:"textarea" }
];

function render(){
    const s = steps[step];
    const c = document.getElementById("step-container");

    // Fortschritt
    document.getElementById("step-count").innerText = `Schritt ${step+1} / ${steps.length}`;
    document.getElementById("bar-fill").style.width = ((step+1)/steps.length*100)+"%";

    c.innerHTML = `<h2>${s.q}</h2>`;

    if(s.type==="radio"){
        s.options.forEach(o=>{
            c.innerHTML += `<label><input type="radio" name="${s.id}" value="${o}"> ${o}</label>`;
        });
    }

    if(s.type==="text" || s.type==="number"){
        c.innerHTML += `<input type="${s.type}" id="${s.id}">`;
    }

    if(s.type==="textarea"){
        c.innerHTML += `<textarea id="${s.id}"></textarea>`;
    }

    if(s.type==="file"){
        c.innerHTML += `<input type="file" id="${s.id}" multiple>`;
    }

    // WICHTIG: BUTTON IST JETZT DIREKT HIER
    c.innerHTML += `<button class="next-btn" onclick="nextStep()">Weiter</button>`;
}

render();

function nextStep(){
    const s = steps[step];
    let val;

    if(s.type==="radio"){
        const r = document.querySelector(`input[name="${s.id}"]:checked`);
        if(!r) return error();
        val = r.value;
    } else {
        const i = document.getElementById(s.id);
        if(!i.value && s.type!=="file") return error();
        val = s.type==="file" ? i.files : i.value;
    }

    data[s.id] = val;

    if(step === steps.length-1){
        alert("Fertig!");
        console.log(data);
        return;
    }

    step++;
    render();
}

function error(){
    document.getElementById("error").innerText = "Bitte beantworten!";
}