import * as React from "react"
import { ChevronLeft, ChevronRight } from "lucide-react"
import { DayPicker } from "react-day-picker"
import { cn } from "cn"
import { buttonVariants } from "@/components/ui/button"

function Calendar({ className, classNames, showOutsideDays = true, ...props }: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("text-sm", className)}
      classNames={{
        months: "relative flex flex-col gap-4",
        month: "flex flex-col gap-3",
        month_caption: "flex h-8 items-center justify-center",
        caption_label: "font-medium",
        nav: "absolute inset-x-0 top-0 flex items-center justify-between",
        button_previous: cn(buttonVariants({ variant: "ghost", size: "icon-sm" }), "z-10"),
        button_next: cn(buttonVariants({ variant: "ghost", size: "icon-sm" }), "z-10"),
        month_grid: "border-collapse",
        weekdays: "flex",
        weekday: "w-9 text-[0.75rem] font-normal text-muted-foreground",
        week: "mt-1 flex",
        day: "size-9 p-0 text-center",
        day_button: cn(
          buttonVariants({ variant: "ghost" }),
          "size-9 p-0 font-normal tabular-nums aria-selected:opacity-100"
        ),
        selected: "[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary",
        today: "[&>button]:ring-1 [&>button]:ring-foreground/20",
        outside: "text-muted-foreground opacity-50",
        disabled: "pointer-events-none text-muted-foreground opacity-40",
        hidden: "invisible",
        ...classNames,
      }}
      components={{
        Chevron: ({ orientation }) =>
          orientation === "left" ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />,
      }}
      {...props}
    />
  )
}

export { Calendar }
