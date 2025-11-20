const { mount } = require("@vue/test-utils");
const Pagination = require("../Pagination.vue");

describe("Pagination", () => {
  let wrapper;

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
  });
  describe("Computed Properties", () => {
    describe("totalPages", () => {
      it("calculates correctly for exact divisions", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
          },
        });

        expect(wrapper.vm.totalPages).toBe(4);
      });

      it("calculates correctly with remainders", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 105,
            itemsPerPage: 25,
          },
        });

        expect(wrapper.vm.totalPages).toBe(5);
      });

      it("returns minimum of 1 page when totalItems is 0", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 0,
          },
        });

        expect(wrapper.vm.totalPages).toBe(1);
      });

      it("returns 1 page when totalItems < itemsPerPage", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 10,
            itemsPerPage: 25,
          },
        });

        expect(wrapper.vm.totalPages).toBe(1);
      });
    });

    describe("startItem and endItem", () => {
      it("returns 0 for startItem when totalItems is 0", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 0,
          },
        });

        expect(wrapper.vm.startItem).toBe(0);
        expect(wrapper.vm.endItem).toBe(0);
      });

      it("calculates correctly on first page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            currentPage: 1,
          },
        });

        expect(wrapper.vm.startItem).toBe(1);
        expect(wrapper.vm.endItem).toBe(25);
      });

      it("calculates correctly on middle page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            currentPage: 2,
          },
        });

        expect(wrapper.vm.startItem).toBe(26);
        expect(wrapper.vm.endItem).toBe(50);
      });

      it("calculates correctly on last page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            currentPage: 4,
          },
        });

        expect(wrapper.vm.startItem).toBe(76);
        expect(wrapper.vm.endItem).toBe(100);
      });

      it("caps endItem at totalItems on partial last page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 102,
            itemsPerPage: 25,
            currentPage: 5,
          },
        });

        expect(wrapper.vm.startItem).toBe(101);
        expect(wrapper.vm.endItem).toBe(102);
      });
    });

    describe("visiblePages", () => {
      it("shows all pages when total <= maxVisiblePages", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            maxVisiblePages: 5,
          },
        });

        // 100 / 25 = 4 pages, max 5 = show all 4
        expect(wrapper.vm.visiblePages).toEqual([1, 2, 3, 4]);
      });

      it("shows sliding window centered on current page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 250, // 10 pages
            itemsPerPage: 25,
            currentPage: 5,
            maxVisiblePages: 5,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([3, 4, 5, 6, 7]);
      });

      it("adjusts window at start", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 250, // 10 pages
            itemsPerPage: 25,
            currentPage: 1,
            maxVisiblePages: 5,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([1, 2, 3, 4, 5]);
      });

      it("adjusts window at start when on page 2", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 250, // 10 pages
            itemsPerPage: 25,
            currentPage: 2,
            maxVisiblePages: 5,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([1, 2, 3, 4, 5]);
      });

      it("adjusts window at end", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 250, // 10 pages
            itemsPerPage: 25,
            currentPage: 10,
            maxVisiblePages: 5,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([6, 7, 8, 9, 10]);
      });

      it("adjusts window at end when on page 9", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 250, // 10 pages
            itemsPerPage: 25,
            currentPage: 9,
            maxVisiblePages: 5,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([6, 7, 8, 9, 10]);
      });

      it("handles single page scenario", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 10,
            itemsPerPage: 25,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([1]);
      });

      it("handles maxVisiblePages = 1", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            currentPage: 2,
            maxVisiblePages: 1,
          },
        });

        expect(wrapper.vm.visiblePages).toEqual([2]);
      });
    });
  });

  describe("User Interactions", () => {
    describe("Navigation Buttons", () => {
      it("clicking first button goes to page 1", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 3,
          },
        });

        await wrapper.find(".pagination-first").trigger("click");

        expect(wrapper.emitted("page-change")).toBeTruthy();
        expect(wrapper.emitted("page-change")[0][0]).toEqual({
          page: 1,
          itemsPerPage: 25,
        });
      });

      it("clicking previous button goes to previous page", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 3,
          },
        });

        await wrapper.find(".pagination-prev").trigger("click");

        expect(wrapper.emitted("page-change")[0][0]).toEqual({
          page: 2,
          itemsPerPage: 25,
        });
      });

      it("clicking next button goes to next page", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 2,
          },
        });

        await wrapper.find(".pagination-next").trigger("click");

        expect(wrapper.emitted("page-change")[0][0]).toEqual({
          page: 3,
          itemsPerPage: 25,
        });
      });

      it("clicking last button goes to last page", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 1,
          },
        });

        await wrapper.find(".pagination-last").trigger("click");

        expect(wrapper.emitted("page-change")[0][0]).toEqual({
          page: 4,
          itemsPerPage: 25,
        });
      });

      it("clicking page number button goes to that page", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 1,
          },
        });

        const pageButtons = wrapper.findAll(".pagination-page");
        await pageButtons[2].trigger("click"); // Click page 3

        expect(wrapper.emitted("page-change")[0][0].page).toBe(3);
      });
    });

    describe("Items Per Page Selector", () => {
      it("changing items-per-page updates local state", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
          },
        });

        const select = wrapper.find("#items-per-page");
        await select.setValue("50");

        expect(wrapper.vm.itemsPerPageLocal).toBe(50);
      });

      it("changing items-per-page resets to page 1", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 3,
          },
        });

        const select = wrapper.find("#items-per-page");
        await select.setValue("50");

        expect(wrapper.emitted("page-change")[0][0]).toEqual({
          page: 1,
          itemsPerPage: 50,
        });
      });

      it("changing items-per-page recalculates totalPages", async () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
          },
        });

        expect(wrapper.vm.totalPages).toBe(4);

        await wrapper.find("#items-per-page").setValue("50");

        expect(wrapper.vm.totalPages).toBe(2);
      });
    });

    describe("Disabled States", () => {
      it("disables first and previous buttons on page 1", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            currentPage: 1,
          },
        });

        const firstBtn = wrapper.find(".pagination-first");
        const prevBtn = wrapper.find(".pagination-prev");

        expect(firstBtn.attributes("disabled")).toBeDefined();
        expect(prevBtn.attributes("disabled")).toBeDefined();
      });

      it("disables next and last buttons on last page", () => {
        wrapper = mount(Pagination, {
          props: {
            totalItems: 100,
            itemsPerPage: 25,
            currentPage: 4,
          },
        });

        const nextBtn = wrapper.find(".pagination-next");
        const lastBtn = wrapper.find(".pagination-last");

        expect(nextBtn.attributes("disabled")).toBeDefined();
        expect(lastBtn.attributes("disabled")).toBeDefined();
      });
    });
  });

  describe("Event Emissions", () => {
    it("emits page-change with correct structure", async () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 100,
          currentPage: 1,
        },
      });

      await wrapper.find(".pagination-next").trigger("click");

      expect(wrapper.emitted("page-change")).toBeTruthy();
      expect(wrapper.emitted("page-change")).toHaveLength(1);
      expect(wrapper.emitted("page-change")[0][0]).toHaveProperty("page");
      expect(wrapper.emitted("page-change")[0][0]).toHaveProperty(
        "itemsPerPage",
      );
    });

    it("emits multiple events for multiple interactions", async () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 100,
          currentPage: 1,
        },
      });

      await wrapper.find(".pagination-next").trigger("click");
      await wrapper.vm.$nextTick();

      // Update props to simulate parent updating currentPage
      await wrapper.setProps({ currentPage: 2 });

      await wrapper.find(".pagination-next").trigger("click");
      await wrapper.vm.$nextTick();

      // Update props again
      await wrapper.setProps({ currentPage: 3 });

      await wrapper.find(".pagination-prev").trigger("click");

      expect(wrapper.emitted("page-change")).toHaveLength(3);
    });

    it("items-per-page change emits with page=1", async () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 100,
          currentPage: 3,
        },
      });

      await wrapper.find("#items-per-page").setValue("50");

      expect(wrapper.emitted("page-change")[0][0].page).toBe(1);
    });
  });
  describe("Edge Cases", () => {
    it("handles totalItems = 0 gracefully", () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 0,
        },
      });

      expect(wrapper.find(".pagination-info").text()).toBe(
        "Showing 0-0 of 0 items",
      );
      expect(wrapper.vm.totalPages).toBe(1);
    });

    it("handles single page with all buttons disabled", () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 10,
          itemsPerPage: 25,
        },
      });

      const firstBtn = wrapper.find(".pagination-first");
      const prevBtn = wrapper.find(".pagination-prev");
      const nextBtn = wrapper.find(".pagination-next");
      const lastBtn = wrapper.find(".pagination-last");

      expect(firstBtn.attributes("disabled")).toBeDefined();
      expect(prevBtn.attributes("disabled")).toBeDefined();
      expect(nextBtn.attributes("disabled")).toBeDefined();
      expect(lastBtn.attributes("disabled")).toBeDefined();
    });
  });

  describe("Active States", () => {
    it("highlights current page button with active class", () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 100,
          currentPage: 2,
        },
      });

      const pageButtons = wrapper.findAll(".pagination-page");
      const activeButtons = pageButtons.filter((btn) =>
        btn.classes().includes("active"),
      );

      expect(activeButtons).toHaveLength(1);
      expect(activeButtons[0].text()).toBe("2");
    });

    it("updates active class when page changes", async () => {
      wrapper = mount(Pagination, {
        props: {
          totalItems: 100,
          currentPage: 1,
        },
      });

      await wrapper.setProps({ currentPage: 3 });

      const pageButtons = wrapper.findAll(".pagination-page");
      const activeButtons = pageButtons.filter((btn) =>
        btn.classes().includes("active"),
      );

      expect(activeButtons).toHaveLength(1);
      expect(activeButtons[0].text()).toBe("3");
    });
  });
});
