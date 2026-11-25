// FORM VALIDATION
document.getElementById("studentForm").addEventListener("submit", function (event) {

    const fields = [
        "student_number",
        "first_name",
        "last_name",
        "course",
        "year_level",
        "status"
    ];

    for (const id of fields) {
        const input = document.getElementById(id);

        if (input.value.trim() === "") {
            alert("Please complete all required fields.");
            input.focus();
            event.preventDefault();
            return;
        }
    }
});


// DELETE CONFIRMATION
document.querySelectorAll(".delete-form").forEach(function (form) {

    form.addEventListener("submit", function (event) {

        const confirmation = confirm(
            "Are you sure you want to delete this student record?"
        );

        if (!confirmation) {
            event.preventDefault();
        }
    });
});
