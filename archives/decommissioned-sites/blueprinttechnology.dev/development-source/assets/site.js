"use strict";

const menuToggle = document.querySelector(".menu-toggle");
const primaryNav = document.querySelector("#primary-nav");

if (menuToggle && primaryNav) {
  const closeMenu = () => {
    menuToggle.setAttribute("aria-expanded", "false");
    primaryNav.classList.remove("is-open");
  };

  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!isOpen));
    primaryNav.classList.toggle("is-open", !isOpen);
  });

  primaryNav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeMenu);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeMenu();
  });
}

const stage = document.querySelector(".hero-stage");
const finePointer = window.matchMedia("(pointer: fine)");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

if (stage && finePointer.matches && !reduceMotion.matches) {
  let pendingFrame = 0;

  stage.addEventListener("pointermove", (event) => {
    if (pendingFrame) cancelAnimationFrame(pendingFrame);
    pendingFrame = requestAnimationFrame(() => {
      const bounds = stage.getBoundingClientRect();
      const x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
      const y = ((event.clientY - bounds.top) / bounds.height) * 2 - 1;
      stage.style.setProperty("--pointer-x", (x * -8).toFixed(1) + "px");
      stage.style.setProperty("--pointer-y", (y * -6).toFixed(1) + "px");
      stage.style.setProperty("--rotate-x", (y * -1.4).toFixed(2) + "deg");
      stage.style.setProperty("--rotate-y", (x * 1.7).toFixed(2) + "deg");
    });
  });

  stage.addEventListener("pointerleave", () => {
    if (pendingFrame) cancelAnimationFrame(pendingFrame);
    pendingFrame = 0;
    stage.style.setProperty("--pointer-x", "0px");
    stage.style.setProperty("--pointer-y", "0px");
    stage.style.setProperty("--rotate-x", "0deg");
    stage.style.setProperty("--rotate-y", "0deg");
  });
}
