---
paths:
  - 'resources/js/pages/homepage/**'
---

# Homepage

## Do not cover student timetables with the global loader
The homepage-wide LoadingAnimation overlay must stay hidden for every `/students-timetables/*` route. Those pages use local loading states; background requests and HMR must not cover the timetable UI with centered loading dots.
