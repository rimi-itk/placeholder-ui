import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["image", "url"];

    connect() {
        this.imageTarget.addEventListener("load", () => {
            this.element.classList.remove("loading");
        });
        this.update();
    }

    update() {
        this.element.classList.add("loading");

        const queryString = new URLSearchParams(
            new FormData(this.element),
        ).toString();

        const url = new URL(this.element.action);
        new URLSearchParams(new FormData(this.element)).forEach(
            (value, name) => {
                if (value) {
                    url.searchParams.set(name, value);
                }
            },
        );

        this.imageTarget.src = url.toString();
        this.urlTarget.href = url.toString();
        this.urlTarget.innerHTML = url.toString();
    }
}
