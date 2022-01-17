let targetElement = document.getElementById('demo-wrapper');
validator.setValidatorApiUrl(targetElement.dataset.api);
validator.setValidatorSpecsUrl(targetElement.dataset.specs);
validator.createDemoApplication({
    targetElement: targetElement
});
